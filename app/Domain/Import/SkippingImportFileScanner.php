<?php
/**
 * lel since 12.08.18
 */

namespace App\Domain\Import;

use App\ImportFile;

class SkippingImportFileScanner extends ImportFileScanner
{
    protected function downloadAndCreateImportFile(
        string $importFileType,
        string $remoteFilePath,
        string $originalFilename
    ): ImportFile {
        $importFile = new ImportFile([
            'type' => $importFileType,
            'original_filename' => $originalFilename,
        ]);
        $importFile->save();

        return $importFile;
    }
}
