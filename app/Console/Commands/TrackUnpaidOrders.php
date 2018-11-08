<?php

namespace App\Console\Commands;

use App\Domain\OrderTracking\OrderTracker;
use App\Domain\OrderTracking\UnpaidOrderProvider;
use Illuminate\Console\Command;

class TrackUnpaidOrders extends Command
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
     * @param OrderTracker $orderTracker
     * @param UnpaidOrderProvider $orderProvider
     * @return mixed
     */
    public function handle(OrderTracker $orderTracker, UnpaidOrderProvider $orderProvider)
    {
        $orderTracker->track($orderProvider);
    }
}
