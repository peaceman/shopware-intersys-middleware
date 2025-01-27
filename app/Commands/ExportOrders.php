<?php
/**
 * lel since 13.03.20
 */

namespace App\Commands;

use App\Domain\Export\OrderExportType;
use App\Domain\Export\OrderProvider;
use App\Domain\Export\ShopwareOrderReturnProvider;
use App\Domain\Export\ShopwareOrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;

class ExportOrders
{
    private OrderXMLExporter $exporter;

    private OrderProvider $orderSaleProvider;

    private OrderProvider $orderReturnProvider;

    public function __construct(
        OrderXMLExporter $exporter,
        ShopwareOrderSaleProvider $orderSaleProvider,
        ShopwareOrderReturnProvider $orderReturnProvider
    ) {
        $this->exporter = $exporter;
        $this->orderSaleProvider = $orderSaleProvider;
        $this->orderReturnProvider = $orderReturnProvider;
    }

    public function __invoke(): void
    {
        $this->exporter->export(OrderExportType::Sale, $this->orderSaleProvider);
        $this->exporter->export(OrderExportType::Return, $this->orderReturnProvider);
    }
}
