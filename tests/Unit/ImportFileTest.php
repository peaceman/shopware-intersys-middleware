<?php
/**
 * lel since 12.08.18
 */

namespace Tests\Unit;


use App\ImportFile;
use Tests\TestCase;

class ImportFileTest extends TestCase
{
    public function testFreshImportFileWithStoragePathQualifiesForImport()
    {
        $importFile = new ImportFile([
            'storage_path' => 'foobar',
        ]);

        static::assertTrue($importFile->qualifiesForImport());
    }

    public function testFreshImportFileWithoutStoragePathDoesntQualifyForImport()
    {
        $importFile = new ImportFile();

        static::assertFalse($importFile->qualifiesForImport());
    }

    public function testProcessedImportFileWithStoragePathDoesntQualifyForImport()
    {
        $importFile = new ImportFile([
            'storage_path' => 'foobar',
            'processed_at' => now(),
        ]);

        static::assertFalse($importFile->qualifiesForImport());
    }

    public function testProcessedImportFileWithoutStoragePathDoesntQualifyForImport()
    {
        $importFile = new ImportFile([
            'processed_at' => now(),
        ]);

        static::assertFalse($importFile->qualifiesForImport());
    }
}