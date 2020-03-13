<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\SendDailyOrderOverview;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDailyOrderOverviewJob implements ShouldQueue
{
    public function handle(SendDailyOrderOverview $sendDailyOrderOverview)
    {
        $sendDailyOrderOverview();
    }
}
