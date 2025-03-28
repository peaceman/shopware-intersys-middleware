<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\ExportOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class ExportOrdersJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    public $timeout = 5 * 60;

    public function __construct()
    {
        $this->onConnection('redis-long-running');
    }

    public function handle(ExportOrders $exportOrders): void
    {
        Redis::funnel('export-orders')
            ->limit(1)
            ->releaseAfter($this->timeout)
            ->block(0)
            ->then($exportOrders, function () {
                // Could not obtain lock...
                logger()->info(__CLASS__ . ' Could not obtain lock, delete job');
                $this->delete();
            });
    }
}
