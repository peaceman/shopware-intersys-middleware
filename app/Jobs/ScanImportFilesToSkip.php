<?php

namespace App\Jobs;

use App\Domain\Import\SkippingImportFileScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanImportFilesToSkip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->onConnection('redis-long-running');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SkippingImportFileScanner $importFileScanner)
    {
        $importFileScanner->scan();
    }
}
