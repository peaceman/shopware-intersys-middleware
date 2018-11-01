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
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
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

    protected function setUp()
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('intersys');

        $this->localFS = Storage::disk('local');
        $this->remoteFS = Storage::disk('intersys');
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
            string $type, \DateTimeImmutable $exportDate, Order $order, array $orderArticles
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
            string $type, \DateTimeImmutable $exportDate, Order $order, array $orderArticles
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
        static::assertEquals(['orderStatusId' => 42], $requestData);

        $request = $container[1]['request'];
        static::assertEquals('/api/orders/6', $request->getUri()->getPath());
        $requestData = json_decode((string)$request->getBody(), true);
        static::assertEquals(['orderStatusId' => 42], $requestData);
    }

    protected function compareDateTime(\DateTimeInterface $dateTimeA, \DateTimeInterface $dateTimeB)
    {
        return $dateTimeA->format(\DateTime::ISO8601) === $dateTimeB->format(\DateTime::ISO8601);
    }

    protected function generateOrdersForSaleExport(): array
    {
        $orders = [];

        // first order
        $order = new Order([
            'id' => 5,
            'number' => '23235',
            'orderTime' => \DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(\DateTime::ISO8601),
        ]);

        $order->setArticles([
            new OrderArticle([
                'articleNumber' => 'ABC123',
                'price' => 23.5,
                'quantity' => 23,
            ]),
            new OrderArticle([
                'articleNumber' => 'ABC127',
                'price' => 23.5,
                'quantity' => 23,
            ])
        ]);

        $orders[] = $order;

        // second order
        $order = new Order([
            'id' => 6,
            'number' => '23236',
            'orderTime' => \DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(\DateTime::ISO8601),
        ]);

        $order->setArticles([
            new OrderArticle([
                'articleNumber' => 'ABC127',
                'price' => 23.5,
                'quantity' => 5,
            ])
        ]);

        $orders[] = $order;

        return $orders;
    }
}
