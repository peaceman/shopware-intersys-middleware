<?php
/**
 * lel since 11.08.18
 */

namespace App\Jobs;

use App\Domain\Import\BaseXMLReader;
use App\ImportFile;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

class ParseBaseXML implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var ImportFile
     */
    protected $importFile;

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

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(LoggerInterface $logger, Dispatcher $dispatcher)
    {
        $baseXMLReader = new BaseXMLReader($logger, Storage::disk('local'));

        $modelXMLDataGenerator = $baseXMLReader($this->importFile);

        foreach ($modelXMLDataGenerator as $modelXMLData) {
            $dispatcher->dispatch(new ParseModelXML($modelXMLData));
        }
    }
}
