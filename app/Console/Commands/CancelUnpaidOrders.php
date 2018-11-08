<?php

namespace App\Console\Commands;

use App\Domain\OrderTracking\OrdersToCancelProvider;
use App\Domain\OrderTracking\UnpaidOrderCanceller;
use Illuminate\Console\Command;

class CancelUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:cancel-unpaid-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel unpaid orders';

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
     * @param UnpaidOrderCanceller $unpaidOrderCanceller
     * @param OrdersToCancelProvider $orderProvider
     * @return mixed
     */
    public function handle(UnpaidOrderCanceller $unpaidOrderCanceller, OrdersToCancelProvider $orderProvider)
    {
        $unpaidOrderCanceller->cancel($orderProvider);
    }
}
