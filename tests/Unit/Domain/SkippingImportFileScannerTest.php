<?php
/**
 * lel since 12.08.18
 */

namespace Tests\Unit\Domain;


use App\Domain\Import\SkippingImportFileScanner;
use App\ImportFile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Psr\Log\NullLogger;
use Tests\TestCase;

class SkippingImportFileScannerTest extends TestCase
{
    use DatabaseMigrations;

    public function testSkippingFiles()
    {
        Storage::fake('local');
        Storage::fake('intersys');

        $localFS = Storage::disk('local');
        $remoteFS = Storage::disk('intersys');

        $remoteFS->put('outoforder.xml', 'no');
        $remoteFS->put('base/lel.xml', 'xml base dinge');
        $remoteFS->put('base/lel2.xml', 'xml base dinge 2');
        $remoteFS->put('base/existing.xml', 'xml base existing');
        $remoteFS->put('delta/lel.xml', 'xml delta dinge');
        $remoteFS->put('delta/existing.xml', 'xml delta existing');

        (new ImportFile(['type' => 'base', 'original_filename' => 'existing.xml', 'storage_path' => 'meh']))->save();
        (new ImportFile(['type' => 'delta', 'original_filename' => 'existing.xml', 'storage_path' => 'meh']))->save();

        $importFileScanner = new SkippingImportFileScanner(new NullLogger(), $localFS, $remoteFS);
        $importFileScanner->setBaseFileFolder('base');
        $importFileScanner->setDeltaFileFolder('delta');
        $importFiles = $importFileScanner->scan();

        static::assertTrue(is_iterable($importFiles), 'The result not iterable');
        static::assertCount(3, $importFiles);

        $expectedTypes = ['base', 'base', 'delta'];
        $actualTypes = collect($importFiles)->pluck('type')->sort()->values()->all();
        static::assertEquals($expectedTypes, $actualTypes);

        $expectedOriginalFilenames = ['lel.xml', 'lel.xml', 'lel2.xml'];
        $actualOriginalFilenames = collect($importFiles)->pluck('original_filename')->all();
        static::assertEquals($expectedOriginalFilenames, $actualOriginalFilenames);

        $actualStoragePaths = collect($importFiles)->pluck('storage_path')->all();
        static::assertContainsOnly('null', $actualStoragePaths);
    }
}