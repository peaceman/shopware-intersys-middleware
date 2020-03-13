<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\TrackUnpaidOrders;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrackUnpaidOrdersJob implements ShouldQueue
{
    public function handle(TrackUnpaidOrders $trackUnpaidOrders): void
    {
        $trackUnpaidOrders();
    }
}
