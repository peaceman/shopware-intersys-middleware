<?php

namespace App\Jobs;

use App\Domain\Import\ImportFileScanner;
use App\ImportFile;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Psr\Log\LoggerInterface;

class ScanImportFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ImportFileScanner $importFileScanner)
    {
        $importFiles = $importFileScanner->scan();
        if ($importFiles->isEmpty()) return;

        $importFile = $importFiles->shift();
        dispatch(new ParseBaseXML($importFile))
            ->chain($importFiles->map(function (ImportFile $importFile) {
                return new ParseBaseXML($importFile);
            }));
    }
}
