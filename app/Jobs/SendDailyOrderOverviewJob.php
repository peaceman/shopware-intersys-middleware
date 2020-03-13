<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\SendDailyOrderOverview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDailyOrderOverviewJob implements ShouldQueue
{
    use Queueable;

    public function handle(SendDailyOrderOverview $sendDailyOrderOverview): void
    {
        $sendDailyOrderOverview();
    }
}
