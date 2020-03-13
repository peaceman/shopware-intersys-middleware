<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\ExportOrders;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExportOrdersJob implements ShouldQueue
{
    public function handle(ExportOrders $exportOrders): void
    {
        $exportOrders();
    }
}
