<?php
/**
 * lel since 12.08.18
 */

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use SplFileInfo;

class ImportFileScanner
{
    protected LoggerInterface $logger;
    protected Filesystem $localFS;
    protected Filesystem $remoteFS;
    protected ?string $folder;

    /**
     * ImportFileScanner constructor.
     * @param LoggerInterface $logger
     * @param Filesystem $localFS
     * @param Filesystem $remoteFS
     */
    public function __construct(LoggerInterface $logger, Filesystem $localFS, Filesystem $remoteFS)
    {
        $this->logger = $logger;
        $this->localFS = $localFS;
        $this->remoteFS = $remoteFS;
    }

    public function setFolder(?string $folder): void
    {
        $this->folder = $folder;
    }

    /**
     * @return Collection<int, ImportFile>
     */
    public function scan(): Collection
    {
        return collect()
            ->merge(rescue(fn (): Collection => $this->scanForFiles(), []))
            ->sortBy(fn (ImportFile $file): string => Str::remove(['Base', 'Delta'], $file->original_filename));
    }

    /**
     * @return Collection<int, ImportFile>
     */
    protected function scanForFiles(): Collection
    {
        $this->logger->info(__METHOD__ . ' Start scanning', ['folder' => $this->folder]);
        $startTime = microtime(true);

        $files = $this->remoteFS->files($this->folder);

        $importFiles = collect($files)
            ->map(function (string $filePath) {
                $filename = $this->stripFolder($filePath);

                if (!$importFileType = $this->detectImportFileType($filename)) {
                    $this->logger->warning(__METHOD__ . ' Failed to detect the import file type', [
                        'filename' => $filename,
                        'path' => $filePath,
                    ]);

                    return null;
                }

                $importFileExists = ImportFile::type($importFileType)->where('original_filename', $filename)->exists();
                if ($importFileExists) return null;

                return $this->downloadAndCreateImportFile($importFileType, $filePath, $filename);
            })
            ->filter();

        $this->logger->info(__METHOD__ . ' Finished scanning', [
            'folder' => $this->folder,
            'filesFound' => $importFiles->count(),
            'elapsed' => microtime(true) - $startTime
        ]);

        return $importFiles;
    }

    protected function stripFolder(string $filePath): string
    {
        $fileInfo = new SplFileInfo($filePath);
        return $fileInfo->getFilename();
    }

    protected function downloadAndCreateImportFile(
        string $importFileType,
        string $remoteFilePath,
        string $originalFilename
    ): ImportFile {
        $startDownloadTime = microtime(true);
        $this->logger->info(__METHOD__ . ' Start downloading', ['filePath' => $remoteFilePath]);
        $fileStream = $this->remoteFS->readStream($remoteFilePath);
        $localFilename = Str::random(40);
        $this->localFS->put($localFilename, $fileStream);

        $this->logger->info(__METHOD__ . ' Finished downloading', [
            'filePath' => $remoteFilePath,
            'elapsed' => microtime(true) - $startDownloadTime,
        ]);

        $importFile = new ImportFile([
            'type' => $importFileType,
            'original_filename' => $originalFilename,
            'storage_path' => $localFilename,
        ]);
        $importFile->save();

        return $importFile;
    }

    private function detectImportFileType(string $filename): ?string
    {
        if (Str::startsWith($filename, 'Base'))
            return ImportFile::TYPE_BASE;

        if (Str::startsWith($filename, 'Delta'))
            return ImportFile::TYPE_DELTA;

        return null;
    }
}
