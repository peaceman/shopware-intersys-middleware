<?php

namespace App\Console\Commands;

use App\Commands\TrackUnpaidOrders;
use App\Domain\OrderTracking\OrderTracker;
use App\Domain\OrderTracking\UnpaidOrderProvider;
use Illuminate\Console\Command;

class TrackUnpaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:track-unpaid-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Track unpaid orders';

    /**
     * Execute the console command.
     *
     * @param OrderTracker $orderTracker
     * @param UnpaidOrderProvider $orderProvider
     * @return mixed
     */
    public function handle(TrackUnpaidOrders $trackUnpaidOrders)
    {
        $trackUnpaidOrders();
    }
}
