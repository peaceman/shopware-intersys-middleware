<?php

namespace App\Jobs;

use App\Domain\Import\ImportFileScanner;
use App\ImportFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ScanImportFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60 * 30;

    public $tries = 1;

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
    public function handle(ImportFileScanner $importFileScanner)
    {
        Redis::funnel('scan-import-files')
            ->limit(1)
            ->releaseAfter($this->timeout)
            ->then(function () use ($importFileScanner) {
                $importFiles = $importFileScanner->scan();
                if ($importFiles->isEmpty()) return;

                $importFile = $importFiles->shift();
                dispatch(new ParseBaseXML($importFile))
                    ->chain($importFiles->map(function (ImportFile $importFile) {
                        return new ParseBaseXML($importFile);
                    }));
            }, function () {
                // Could not obtain lock...
                logger()->info('Could not obtain lock, delete job');
                $this->delete();
            });
    }
}
