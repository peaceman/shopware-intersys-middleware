<?php
/**
 * lel since 11.08.18
 */


namespace App\Jobs;

use App\Domain\Import\ModelXMLData;
use App\Domain\Import\ModelXMLImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ParseModelXML implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var ModelXMLData
     */
    protected $modelXMLData;

    /**
     * Create a new job instance.
     *
     * @param ModelXMLData $modelXMLData
     */
    public function __construct(ModelXMLData $modelXMLData)
    {
        $this->modelXMLData = $modelXMLData;
    }

    /**
     * Execute the job.
     *
     * @param ModelXMLImporter $importer
     * @return void
     */
    public function handle(ModelXMLImporter $importer)
    {
        $importer->import($this->modelXMLData);
    }
}
