<?php

namespace App\Console;

use App\Console\Commands\DeleteOldImportFiles;
use App\Jobs\DeactivateArticlesJob;
use App\Jobs\ExportOrdersJob;
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
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new ScanImportFiles())->everyMinute();
//        $schedule->job(DeactivateArticlesJob::class)->dailyAt('06:33');
        $schedule->job(ExportOrdersJob::class)->everyMinute();
        $schedule->command(DeleteOldImportFiles::class)->weekly();
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
