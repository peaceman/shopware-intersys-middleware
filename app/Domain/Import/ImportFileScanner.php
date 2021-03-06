<?php
/**
 * lel since 12.08.18
 */

namespace App\Domain\Import;


use App\ImportFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Flysystem\Adapter\Ftp;
use Psr\Log\LoggerInterface;
use SplFileInfo;

class ImportFileScanner
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Filesystem
     */
    protected $localFS;

    /**
     * @var Filesystem
     */
    protected $remoteFS;

    /**
     * @var string|null
     */
    protected $baseFileFolder;

    /**
     * @var string|null
     */
    protected $deltaFileFolder;

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

    public function setBaseFileFolder(?string $baseFileFolder): void
    {
        $this->baseFileFolder = $baseFileFolder;
    }

    public function setDeltaFileFolder(?string $deltaFileFolder): void
    {
        $this->deltaFileFolder = $deltaFileFolder;
    }

    public function scan(): Collection
    {
        return collect()
            ->merge(rescue(function () {
                return $this->scanForNewBaseFiles();
            }, []))
            ->merge(rescue(function () {
                return $this->scanForNewDeltaFiles();
            }, []))
            ->sortBy('original_filename');
    }

    protected function scanForNewBaseFiles(): Collection
    {
        return $this->scanForFiles($this->baseFileFolder, 'base');
    }

    protected function scanForNewDeltaFiles(): Collection
    {
        return $this->scanForFiles($this->deltaFileFolder, 'delta');
    }

    protected function scanForFiles(?string $folder, string $importFileType): Collection
    {
        $this->logger->info(__METHOD__ . ' Start scanning', ['folder' => $folder]);
        $startTime = microtime(true);

        // reconnect before using the connection until
        // https://github.com/thephpleague/flysystem/issues/1128#issuecomment-586855744
        $this->reconnectRemoteFS($this->remoteFS);
        $files = $this->remoteFS->files($folder);

        $importFiles = collect($files)
            ->map(function (string $filePath) use ($importFileType) {
                $filename = $this->stripFolder($filePath);
                $importFileExists = ImportFile::type($importFileType)->where('original_filename', $filename)->exists();
                if ($importFileExists) return null;

                return $this->downloadAndCreateImportFile($importFileType, $filePath, $filename);
            })
            ->filter();

        $this->logger->info(__METHOD__ . ' Finished scanning', [
            'folder' => $folder,
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
    ): ImportFile
    {
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

    protected function reconnectRemoteFS(Filesystem $filesystem): void
    {
        if (!$filesystem instanceof FilesystemAdapter) {
            return;
        }

        /** @var FilesystemAdapter $filesystem */
        $fsDriver = $filesystem->getDriver();

        if (!$fsDriver instanceof \League\Flysystem\Filesystem) {
            return;
        }

        /** @var \League\Flysystem\Filesystem $fsDriver */
        $fsAdapter = $fsDriver->getAdapter();

        if (!$fsAdapter instanceof Ftp) {
            return;
        }

        /** @var Ftp $fsAdapter */
        $fsAdapter->disconnect();
        $fsAdapter->connect();
    }
}
