<?php
/**
 * lel since 11.08.18
 */

namespace App\Domain\Import;

use App\ImportFile;
use Generator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use League\Csv\Reader;
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

        yield from match (true) {
            Str::endsWith($importFile->original_filename, '.xml') => $this->readXML($importFile),
            Str::endsWith($importFile->original_filename, '.csv') => $this->readCSV($importFile),
            default => [],
        };

        $importFile->update(['processed_at' => now()]);
    }

    public function readXML(ImportFile $importFile): Generator
    {
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

        $elapsedTime = microtime(true) - $startTime;
        $this->logger->info(__METHOD__ . ' Finished reading xml file', [
            'importFile' => [
                'originalFilename' => $importFile->original_filename,
                'storagePath' => $importFile->storage_path,
            ],
            'elapsedTime' => $elapsedTime
        ]);
    }

    public function readCSV(ImportFile $importFile): Generator
    {
        $csv = Reader::createFromStream($this->fs->readStream($importFile->storage_path))
            ->setDelimiter(';')
            ->setHeaderOffset(0);

        $lastModelNumber = null;
        $modelRecords = [];

        foreach ($csv as $record) {
            if (!is_null($lastModelNumber)
                && $lastModelNumber !== $record['MODELLNR']
            ) {
                yield new ModelCSV($importFile, $modelRecords);
                $modelRecords = [];
            }

            $lastModelNumber = $record['MODELLNR'];
            $modelRecords[] = $record;
        }

        if (!empty($modelRecords)) {
            yield new ModelCSV($importFile, $modelRecords);
        }
    }
}
