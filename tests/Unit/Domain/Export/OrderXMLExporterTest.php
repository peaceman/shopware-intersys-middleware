<?php
/**
 * lel since 01.11.18
 */
namespace Tests\Unit\Domain\Export;

use App\Domain\Export\FakeOrderProvider;
use App\Domain\Export\Order;
use App\Domain\Export\OrderExportType;
use App\Domain\Export\OrderLineItemDTO;
use App\Domain\Export\OrderXMLExporter;
use App\Domain\Export\OrderXMLGenerator;
use App\Domain\Shopware6API;
use App\OrderExport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Utils\ArgRecorder;

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

    public function testSaleExport(): void
    {
        $orders = [
            $orderDTO = new Order([
                'id' => (string) Str::uuid()->getHex(),
                'orderNumber' => Str::random(16),
                'orderDateTime' => '2024-12-10T15:15:51.954+00:00',
                'lineItems' => [
                    // product without ean
                    [
                        'type' => 'product',
                        'price' => ['unitPrice' => 5, 'totalPrice' => 25],
                        'quantity' => 5,
                        'product' => [
                            'ean' => null,
                        ],
                    ],
                    // valid exportable product
                    [
                        'type' => 'product',
                        'price' => ['unitPrice' => 500, 'totalPrice' => 1500],
                        'quantity' => 3,
                        'product' => [
                            'ean' => Str::random(13),
                        ],
                    ],
                    // voucher
                    [
                        'type' => 'promotion',
                        'quantity' => 1,
                        'product' => null,
                    ]
                ],
            ]),
        ];

        // mocks
        $generator = $this->createMock(OrderXMLGenerator::class);
        $shopwareApi = $this->createMock(Shopware6API::class);

        $shopwareApi->expects(static::once())
            ->method('updateOrder')
            ->with($orderDTO->getId(), static::callback($updateOrderDataRecorder = new ArgRecorder()));

        $generator->expects(static::once())
            ->method('generate')
            ->with(OrderExportType::Sale, static::anything(), $orderDTO, static::callback($lineItemsRecorder = new ArgRecorder()));

        // execution
        $exporter = new OrderXMLExporter(
            new NullLogger(),
            $this->localFS, $this->remoteFS,
            $generator,
            $shopwareApi,
        );

        $exporter->setBaseFolder('order');

        $exporter->setOrderNumberPrefix($orderNumberPrefix = 'foo-the-bar');
        $exporter->export(OrderExportType::Sale, new FakeOrderProvider($orders));

        // assertions

        // check existing remote files
        static::assertTrue($this->remoteFS->exists(
            "order/order-$orderNumberPrefix-{$orderDTO->getOrderNumber()}S_Webshop_2024-12-10_15-15-51.xml"
        ));

        OrderExport::all()->each(function (OrderExport $orderExport) {
            static::assertTrue($this->localFS->exists($orderExport->storage_path));
        });

        static::assertDatabaseHas('order_exports', [
            'sw_order_number' => $orderDTO->getOrderNumber(),
            'sw_order_id' => $orderDTO->getId(),
        ]);

        // check order update data
        $updateOrderData = $updateOrderDataRecorder->latest();
        static::assertEquals($orderDTO->getId(), $updateOrderData['id']);
        static::assertNotNull($updateOrderData['intersys']['exportedAt']);

        // check line item filters
        $lineItems = $lineItemsRecorder->latest();
        static::assertCount(1, $lineItems);

        /** @var OrderLineItemDTO $lineItem */
        [$lineItem] = $lineItems;
        static::assertTrue($lineItem->isProduct());
        static::assertNotNull($lineItem->getEan());
    }

    public function testReturnExport(): void
    {
        // todo implement
        static::assertTrue(true);
    }
}
