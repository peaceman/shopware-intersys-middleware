<?php
/**
 * lel since 11.08.18
 */

namespace App\Jobs;

use App\Domain\Import\ImportFileReader;
use App\ImportFile;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory as FsFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

class ReadImportFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ImportFile $importFile;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ImportFile $importFile)
    {
        $this->onConnection('redis-long-running');
        $this->importFile = $importFile;
    }

    public function handle(LoggerInterface $logger, Dispatcher $dispatcher, FsFactory $fsFactory): void
    {
        $importFileReader = new ImportFileReader($logger, $fsFactory->disk('local'));

        $modelDataGenerator = $importFileReader($this->importFile);

        foreach ($modelDataGenerator as $modelData) {
            $dispatcher->dispatch(new ImportModel($modelData));
        }
    }
}
