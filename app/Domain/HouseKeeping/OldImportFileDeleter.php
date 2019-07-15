<?php
/**
 * lel since 2019-07-15
 */

namespace App\Domain\HouseKeeping;

use App\ImportFile;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;

class OldImportFileDeleter
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Filesystem */
    protected $localFS;

    /** @var ConnectionInterface */
    protected $db;

    public function __construct(LoggerInterface $logger, Filesystem $localFS, ConnectionInterface $db)
    {
        $this->localFS = $localFS;
        $this->logger = $logger;
        $this->db = $db;
    }

    public function deleteFiles(OldImportFileProvider $importFileProvider): void
    {
        $startTime = microtime(true);
        $this->logger->info('OldImportFileDeleter: start deleting files');
        $counter = 0;

        /** @var ImportFile $importFile */
        foreach ($importFileProvider->provide() as $importFile) {
            try {
                $this->db->transaction(function () use ($importFile) {
                    $this->deleteImportFile($importFile);
                });

                $counter++;
            } catch (Exception $e) {
                $this->logger->error('OldImportFileDeleter: failed to delete import file', [
                    'importFile' => $importFile->asLoggingContext(),
                    'e' => $e->getMessage(),
                ]);

                report($e);
            }
        }

        $elapsed = microtime(true) - $startTime;
        $this->logger->info('OldImportFileDeleter: finished deleting files', [
            'elapsed' => $elapsed,
            'counter' => $counter,
        ]);
    }

    protected function deleteImportFile(ImportFile $importFile): void
    {
        $storagePath = $importFile->storage_path;
        if (is_null($storagePath)) return;

        $importFile->storage_path = null;
        $importFile->save();

        $this->localFS->delete($storagePath);

        $this->logger->info('OldImportFileDeleter: delete import file', [
            'importFile' => $importFile->asLoggingContext(),
            'storagePath' => $storagePath,
        ]);
    }
}
