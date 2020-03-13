<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\TrackUnpaidOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrackUnpaidOrdersJob implements ShouldQueue
{
    use Queueable;

    public function handle(TrackUnpaidOrders $trackUnpaidOrders): void
    {
        $trackUnpaidOrders();
    }
}
