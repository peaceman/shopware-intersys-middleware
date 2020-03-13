<?php
/**
 * lel since 13.03.20
 */

namespace App\Commands;

use App\Domain\Export\OrderReturnProvider;
use App\Domain\Export\OrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\OrderExport;

class ExportOrders
{
    /**
     * @var OrderXMLExporter
     */
    private $exporter;

    /**
     * @var OrderSaleProvider
     */
    private $orderSaleProvider;

    /**
     * @var OrderReturnProvider
     */
    private $orderReturnProvider;

    public function __construct(
        OrderXMLExporter $exporter,
        OrderSaleProvider $orderSaleProvider,
        OrderReturnProvider $orderReturnProvider
    ) {
        $this->exporter = $exporter;
        $this->orderSaleProvider = $orderSaleProvider;
        $this->orderReturnProvider = $orderReturnProvider;
    }

    public function __invoke(): void
    {
        $this->exporter->export(OrderExport::TYPE_SALE, $this->orderSaleProvider);
        $this->exporter->export(OrderExport::TYPE_RETURN, $this->orderReturnProvider);
    }
}
