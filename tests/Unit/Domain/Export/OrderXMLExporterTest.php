<?php
/**
 * lel since 01.11.18
 */
namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderArticle;
use App\Domain\Export\OrderProvider;
use App\Domain\Export\OrderXMLExporter;
use App\Domain\Export\OrderXMLGenerator;
use App\Domain\ShopwareAPI;
use App\OrderExport;
use App\OrderExportArticle;
use DateTime;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Psr\Log\NullLogger;
use Tests\TestCase;

class OrderXMLExporterTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @var Filesystem
     */
    private $localFS;

    /**
     * @var Filesystem
     */
    private $remoteFS;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('intersys');

        $this->localFS = Storage::disk('local');
        $this->remoteFS = Storage::disk('intersys');
    }

    public function testReturnExport()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $order = $this->generateOrderForReturnExport();
        [$orderArticle] = $order->getArticles();

        $orderExport = new OrderExport();
        $orderExport->type = 'sale';
        $orderExport->storage_path = Str::random(40) . '.xml';
        $orderExport->sw_order_number = $order->getOrderNumber();
        $orderExport->sw_order_id = $order->getID();
        $orderExport->save();

        $orderExportArticle = new OrderExportArticle();
        $orderExportArticle->orderExport()->associate($orderExport);
        $orderExportArticle->sw_article_number = $orderArticle->getArticleNumber();
        $orderExportArticle->date_of_trans = DateTime::createFromFormat('Ymd-His', '20181031-230555');
        $orderExportArticle->save();

        $orderXMLGenerator = Mockery::mock(OrderXMLGenerator::class);
        $orderXMLGenerator->shouldReceive('generate')->withArgs(function (
            string $type, DateTimeImmutable $exportDate, Order $orderToCheck, array $orderArticles
        ) use ($order, $orderExportArticle) {
            if ($type !== OrderExport::TYPE_RETURN) return false;
            if ($orderToCheck !== $order) return false;

            if ($this->compareDateTime($orderArticles[0]['dateOfTrans'], $orderExportArticle->date_of_trans))
                return false;

            $articles = $orderToCheck->getArticles();
            if ($orderArticles[0]['article'] !== $articles[0]) return false;

            return true;
        });

        $shopwareAPI = new ShopwareAPI(new NullLogger(), $client);
        $exporter = new OrderXMLExporter(
            new NullLogger(),
            $this->localFS, $this->remoteFS, $orderXMLGenerator,
            $shopwareAPI
        );

        $exporter->setBaseFolder('order');
        $exporter->setAfterExportStatusSale(42);
        $exporter->setAfterExportStatusReturn(43);
        $exporter->setAfterExportPositionStatusReturn(16);
        $exporter->setOrderPositionStatusRequirementReturn(15);

        $orderProvider = Mockery::mock(OrderProvider::class);
        $orderProvider->expects()->getOrders()->andReturn([$order]);

        $exporter->export(OrderExport::TYPE_RETURN, $orderProvider);

        // check existing remote files
        static::assertTrue($this->remoteFS->exists('order/order-23235R_Webshop_2018-10-31_23-05-55.xml'));

        OrderExport::query()->where('type', OrderExport::TYPE_RETURN)
            ->get()->each(function (OrderExport $orderExport) {
            static::assertTrue($this->localFS->exists($orderExport->storage_path));
        });

        /** @var OrderExport $orderExportReturn */
        $orderExportReturn = OrderExport::query()->where('type', OrderExport::TYPE_RETURN)->first();
        static::assertNotNull($orderExportReturn);
        static::assertEquals($orderExportReturn->sw_order_number, '23235');
        static::assertEquals($orderExportReturn->sw_order_id, 5);
        static::assertEquals(1, $orderExportReturn->orderExportArticles->count());

        [$orderExportArticleReturn] = $orderExportReturn->orderExportArticles;
        static::assertNotNull($orderExportArticleReturn);
        static::assertNotEquals($orderExportArticle->date_of_trans, $orderExportArticleReturn->date_of_trans);

        static::assertDatabaseHas('order_exports', [
            'type' => OrderExport::TYPE_RETURN,
            'sw_order_number' => 23235,
            'sw_order_id' => 5,
        ]);

        // check shopware api requests
        static::assertCount(1, $container);

        /** @var Request $request */
        $request = $container[0]['request'];
        static::assertEquals('/api/orders/5', $request->getUri()->getPath());
        $requestData = json_decode((string)$request->getBody(), true);
        static::assertEquals([
            'orderStatusId' => 43,
            'details' => [
                [
                    'id' => 11,
                    'status' => 16,
                ],
                [
                    'id' => 12,
                ],
            ]
        ], $requestData);
    }

    public function testSaleExportWithVouchers()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $stack = HandlerStack::create($mock);

        $client = new Client([
            'handler' => $stack,
        ]);

        $order = $this->generateOrderForSaleExportWithVoucher();
        $orderXMLGenerator = Mockery::mock(OrderXMLGenerator::class);
        $orderXMLGenerator->shouldReceive('generate')->withArgs((function (
            string $type, DateTimeImmutable $exportDate, Order $orderToCheck, array $orderArticles
        ) use ($order) {
            if ($type !== OrderExport::TYPE_SALE) return false;
            if ($order !== $orderToCheck) return false;

            foreach ($order->getArticles() as $article) {
                if (!$article->isVoucher() && abs(($article->getVoucherPercentage() - 0.4) / 0.4) > 0.00001)
                    return false;
            }

            return true;
        }));

        $shopwareAPI = new ShopwareAPI(new NullLogger(), $client);
        $exporter = new OrderXMLExporter(
            new NullLogger(),
            $this->localFS, $this->remoteFS, $orderXMLGenerator,
            $shopwareAPI
        );
        $exporter->setBaseFolder('order');
        $exporter->setAfterExportStatusSale(42);
        $exporter->setAfterExportStatusReturn(43);
        $exporter->setAfterExportPositionStatusReturn(16);

        $orderProvider = Mockery::mock(OrderProvider::class);
        $orderProvider->expects()->getOrders()->andReturn([$order]);

        $exporter->export(OrderExport::TYPE_SALE, $orderProvider);
    }

    public function testSaleExport()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $orders = $this->generateOrdersForSaleExport();

        $orderXMLGenerator = Mockery::mock(OrderXMLGenerator::class);
        $orderXMLGenerator->shouldReceive('generate')->withArgs((function (
            string $type, DateTimeImmutable $exportDate, Order $order, array $orderArticles
        ) use ($orders) {
            if ($type !== OrderExport::TYPE_SALE) return false;
            if ($order !== $orders[0]) return false;

            $articles = $order->getArticles();
            if (!$this->compareDateTime($orderArticles[0]['dateOfTrans'], $orders[0]->getOrderTime())) return false;
            if (!$this->compareDateTime($orderArticles[1]['dateOfTrans'], $orders[0]->getOrderTime())) return false;

            if ($orderArticles[0]['article'] !== $articles[0]) return false;
            if ($orderArticles[1]['article'] !== $articles[1]) return false;

            return true;
        }));

        $orderXMLGenerator->shouldReceive('generate')->withArgs(function (
            string $type, DateTimeImmutable $exportDate, Order $order, array $orderArticles
        ) use ($orders) {
            if ($type !== OrderExport::TYPE_SALE) return false;
            if ($order !== $orders[1]) return false;

            $articles = $order->getArticles();
            if ($this->compareDateTime($orderArticles[0]['dateOfTrans'], $orders[1]->getOrderTime())) return false;
            if ($orderArticles[0]['article'] !== $articles[0]) return false;

            return true;
        });

        $shopwareAPI = new ShopwareAPI(new NullLogger(), $client);
        $exporter = new OrderXMLExporter(
            new NullLogger(),
            $this->localFS, $this->remoteFS, $orderXMLGenerator,
            $shopwareAPI
        );
        $exporter->setBaseFolder('order');
        $exporter->setAfterExportStatusSale(42);
        $exporter->setAfterExportStatusReturn(43);
        $exporter->setAfterExportPositionStatusReturn(16);

        $orderProvider = Mockery::mock(OrderProvider::class);
        $orderProvider->expects()->getOrders()->andReturn($orders);

        $exporter->export(OrderExport::TYPE_SALE, $orderProvider);

        // check existing remote files
        static::assertTrue($this->remoteFS->exists('order/order-23235S_Webshop_2018-10-31_23-05-55.xml'));
        static::assertTrue($this->remoteFS->exists('order/order-23236S_Webshop_2018-10-31_23-05-55.xml'));

        OrderExport::all()->each(function (OrderExport $orderExport) {
            static::assertTrue($this->localFS->exists($orderExport->storage_path));
        });

        static::assertDatabaseHas('order_exports', [
            'sw_order_number' => 23235,
            'sw_order_id' => 5,
        ]);

        static::assertDatabaseHas('order_exports', [
            'sw_order_number' => 23236,
            'sw_order_id' => 6,
        ]);

        static::assertCount(2, $container);

        $request = $container[0]['request'];
        static::assertEquals('/api/orders/5', $request->getUri()->getPath());
        $requestData = json_decode((string)$request->getBody(), true);
        static::assertEquals(['orderStatusId' => 42, 'details' => []], $requestData);

        $request = $container[1]['request'];
        static::assertEquals('/api/orders/6', $request->getUri()->getPath());
        $requestData = json_decode((string)$request->getBody(), true);
        static::assertEquals(['orderStatusId' => 42, 'details' => []], $requestData);
    }

    protected function generateOrdersForSaleExport(): array
    {
        $orders = [];

        // first order
        $order = new Order([
            'id' => 5,
            'number' => '23235',
            'orderTime' => DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(DateTime::ISO8601),
        ]);

        $order->setArticles([
            new OrderArticle([
                'articleNumber' => 'ABC123',
                'price' => 23.5,
                'quantity' => 23,
                'mode' => 0,
            ]),
            new OrderArticle([
                'articleNumber' => 'ABC127',
                'price' => 23.5,
                'quantity' => 23,
                'mode' => 0,
            ])
        ]);

        $orders[] = $order;

        // second order
        $order = new Order([
            'id' => 6,
            'number' => '23236',
            'orderTime' => DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(DateTime::ISO8601),
        ]);

        $order->setArticles([
            new OrderArticle([
                'articleNumber' => 'ABC127',
                'price' => 23.5,
                'quantity' => 5,
                'mode' => 0,
            ])
        ]);

        $orders[] = $order;

        return $orders;
    }

    protected function generateOrderForSaleExportWithVoucher(): Order
    {
        $order = new Order([
            'id' => 5,
            'number' => '23235',
            'orderTime' => DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(DateTime::ISO8601),
        ]);

        $order->setArticles([
            new OrderArticle([
                'articleNumber' => 'ABC123',
                'price' => 23.5,
                'quantity' => 23,
                'mode' => 0,
            ]),
            new OrderArticle([
                'articleNumber' => 'ABC127',
                'price' => 23.5,
                'quantity' => 23,
                'mode' => 0,
            ]),
            new OrderArticle([
                'id' => 13,
                'articleNumber' => 'VOUCHER-0x1',
                'price' => -216.2,
                'quantity' => 2,
                'mode' => 2,
            ])
        ]);

        return $order;
    }

    public function generateOrderForReturnExport(): Order
    {
        $order = new Order([
            'id' => 5,
            'number' => '23235',
            'orderTime' => DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(DateTime::ISO8601),
        ]);

        $order->setArticles([
            new OrderArticle([
                'id' => 11,
                'articleNumber' => 'ABC123',
                'price' => 23.5,
                'quantity' => 23,
                'statusId' => 15,
                'mode' => 0,
            ]),
            new OrderArticle([
                'id' => 12,
                'articleNumber' => 'ABC123',
                'price' => 23.5,
                'quantity' => 23,
                'statusId' => 45,
                'mode' => 0,
            ]),
        ]);

        return $order;
    }
}
