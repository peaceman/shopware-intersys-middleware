<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Commands;

use App\Commands\ExportOrders;
use App\Domain\Export\OrderExportType;
use App\Domain\Export\ShopwareOrderReturnProvider;
use App\Domain\Export\ShopwareOrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\OrderExport;
use Tests\TestCase;

class ExportOrdersTest extends TestCase
{
    public function testExecution(): void
    {
        $orderXMLExporter = $this->createMock(OrderXMLExporter::class);
        $orderSaleProvider = $this->createMock(ShopwareOrderSaleProvider::class);
        $orderReturnProvider = $this->createMock(ShopwareOrderReturnProvider::class);

        $orderXMLExporter->expects(static::exactly(2))
            ->method('export')
            ->withConsecutive(
                [OrderExportType::Sale, $orderSaleProvider],
                [OrderExportType::Return, $orderReturnProvider],
            );

        $exportOrders = $this->app->make(ExportOrders::class, [
            'exporter' => $orderXMLExporter,
            'orderSaleProvider' => $orderSaleProvider,
            'orderReturnProvider' => $orderReturnProvider,
        ]);

        $exportOrders();
    }
}
