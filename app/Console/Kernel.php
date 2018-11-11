<?php

namespace App\Console;

use App\Console\Commands\CancelUnpaidOrders;
use App\Console\Commands\DeactivateArticles;
use App\Console\Commands\ExportOrders;
use App\Console\Commands\SendDailyOrderOverview;
use App\Console\Commands\TrackUnpaidOrders;
use App\Jobs\ScanImportFiles;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new ScanImportFiles())->everyMinute();
        $schedule->command(DeactivateArticles::class)->dailyAt('06:33');
        $schedule->command(ExportOrders::class)->everyMinute();
        $schedule->command(TrackUnpaidOrders::class)->everyMinute();
        $schedule->command(CancelUnpaidOrders::class)->dailyAt('02:23');
        $schedule->command(SendDailyOrderOverview::class)->dailyAt('05:23');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
