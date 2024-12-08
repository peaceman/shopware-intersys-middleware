<?php
/**
 * lel since 11.08.18
 */


namespace App\Jobs;

use App\Domain\Import\ModelDTO;
use App\Domain\Import\ModelImporter;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Psr\Log\LoggerInterface;

class ImportModel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var ModelDTO
     */
    protected $modelData;

    /**
     * Create a new job instance.
     *
     * @param ModelDTO $modelXMLData
     */
    public function __construct(ModelDTO $modelXMLData)
    {
        $this->modelData = $modelXMLData;
    }

    /**
     * Execute the job.
     *
     * @param LoggerInterface $logger
     * @param ModelImporter $importer
     * @return void
     */
    public function handle(LoggerInterface $logger, ModelImporter $importer)
    {
        try {
            $importer->import($this->modelData);
        } catch (Exception $e) {
            $logger->warning('Failed to import article', [
                'e' => $e->getMessage(),
            ]);

            report($e);
        }
    }
}
