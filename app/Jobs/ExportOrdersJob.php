<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\ExportOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExportOrdersJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 5 * 60;

    public function __construct()
    {
        $this->onConnection('redis-long-running');
    }

    public function handle(ExportOrders $exportOrders): void
    {
        $exportOrders();
    }
}
