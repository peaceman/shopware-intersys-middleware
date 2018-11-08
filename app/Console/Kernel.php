<?php

namespace App\Console;

use App\Console\Commands\DeactivateArticles;
use App\Console\Commands\ExportOrders;
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
        $schedule->command(DeactivateArticles::class)->daily('06:33');
        $schedule->job(ExportOrders::class)->everyMinute();
        $schedule->command(TrackUnpaidOrders::class)->everyMinute();
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
