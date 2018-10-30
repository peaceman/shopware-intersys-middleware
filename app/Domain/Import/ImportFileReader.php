<?php
/**
 * lel since 11.08.18
 */

namespace App\Domain\Import;

use App\ImportFile;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use XMLReader;

class ImportFileReader
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Filesystem
     */
    private $fs;

    public function __construct(LoggerInterface $logger, Filesystem $fs)
    {
        $this->logger = $logger;
        $this->fs = $fs;
    }

    public function __invoke(ImportFile $importFile): Generator
    {
        if (!$importFile->qualifiesForImport()) return;

        $this->logger->info(__METHOD__ . ' Open xml file', [
            'importFile' => [
                'originalFilename' => $importFile->original_filename,
                'storagePath' => $importFile->storage_path,
            ],
        ]);
        $startTime = microtime(true);

        $filePath = $this->fs->getDriver()->getAdapter()->getPathPrefix() . '/' . $importFile->storage_path;
        $xmlReader = new XMLReader();
        $xmlReader->open($filePath);

        // move to the first model node
        while ($xmlReader->read() && $xmlReader->name !== 'Model');

        while ($xmlReader->name === 'Model') {
            $modelXML = $xmlReader->readOuterXml();

            yield new ModelXMLData($importFile, $modelXML);
            $xmlReader->next('Model');
        }

        $importFile->update(['processed_at' => now()]);

        $elapsedTime = microtime(true) - $startTime;
        $this->logger->info(__METHOD__ . ' Finished reading xml file', [
            'importFile' => [
                'originalFilename' => $importFile->original_filename,
                'storagePath' => $importFile->storage_path,
            ],
            'elapsedTime' => $elapsedTime
        ]);
    }
}
