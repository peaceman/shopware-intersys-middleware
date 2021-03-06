<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Commands;

use App\Commands\ExportOrders;
use App\Domain\Export\OrderReturnProvider;
use App\Domain\Export\OrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\OrderExport;
use Tests\TestCase;

class ExportOrdersTest extends TestCase
{
    public function testExecution(): void
    {
        $orderXMLExporter = $this->createMock(OrderXMLExporter::class);
        $orderSaleProvider = $this->createMock(OrderSaleProvider::class);
        $orderReturnProvider = $this->createMock(OrderReturnProvider::class);

        $orderXMLExporter->expects(static::exactly(2))
            ->method('export')
            ->withConsecutive(
                [OrderExport::TYPE_SALE, $orderSaleProvider],
                [OrderExport::TYPE_RETURN, $orderReturnProvider],
            );

        $exportOrders = $this->app->make(ExportOrders::class, [
            'exporter' => $orderXMLExporter,
            'orderSaleProvider' => $orderSaleProvider,
            'orderReturnProvider' => $orderReturnProvider,
        ]);

        $exportOrders();
    }
}
