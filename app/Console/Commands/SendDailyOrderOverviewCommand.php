<?php

namespace App\Console\Commands;

use App\Commands\SendDailyOrderOverview;
use Illuminate\Console\Command;

class SendDailyOrderOverviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:send-daily-order-overview';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the daily order overview mail';

    public function handle(SendDailyOrderOverview $sendDailyOrderOverview)
    {
        $sendDailyOrderOverview();
    }
}
