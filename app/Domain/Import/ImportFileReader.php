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

        $xmlReader = XMLReader::XML($this->fs->get($importFile->storage_path));
        if (!$xmlReader) throw new \RuntimeException('Failed to read ImportFile XML');

        /** @var $xmlReader XMLReader */

        // move to the first model node
        while ($xmlReader->read() && $xmlReader->name !== 'Model');

        while ($xmlReader->name === 'Model') {
            $modelXML = $xmlReader->readOuterXml();

            yield new ModelXML($importFile, $modelXML);
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
