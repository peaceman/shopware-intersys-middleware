<?php

namespace App\Console\Commands;

use App\Domain\Export\OrderReturnProvider;
use App\Domain\Export\OrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\OrderExport;
use Illuminate\Console\Command;

class ExportOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:export-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param OrderXMLExporter $exporter
     * @param OrderSaleProvider $orderSaleProvider
     * @param OrderReturnProvider $orderReturnProvider
     */
    public function handle(
        OrderXMLExporter $exporter,
        OrderSaleProvider $orderSaleProvider,
        OrderReturnProvider $orderReturnProvider
    )
    {
        $exporter->export(OrderExport::TYPE_SALE, $orderSaleProvider);
        $exporter->export(OrderExport::TYPE_RETURN, $orderReturnProvider);
    }
}
