<?php
/**
 * lel since 12.08.18
 */

namespace Tests\Unit\Domain;

use App\Domain\Import\ImportFileScanner;
use App\ImportFile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Psr\Log\NullLogger;
use Tests\TestCase;

class ImportFileScannerTest extends TestCase
{
    use DatabaseMigrations;

    public function testDetectsNewImportFiles()
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

        $importFileScanner = new ImportFileScanner(new NullLogger(), $localFS, $remoteFS);
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

        collect($importFiles)->pluck('storage_path')->each(function (string $storagePath) use ($localFS) {
            static::assertTrue($localFS->exists($storagePath), "{$storagePath} does not exist on local disk");
        });
    }

    public function testImportFilesAreOrdered()
    {
        Storage::fake('local');
        Storage::fake('intersys');

        $localFS = Storage::disk('local');
        $remoteFS = Storage::disk('intersys');

        $remoteFS->put('outoforder.xml', 'no');
        $remoteFS->put('base/2018-08-02-03-25.xml', 'xml base dinge');
        $remoteFS->put('base/2018-08-07-15-15.xml', 'xml base dinge 2');
        $remoteFS->put('delta/2018-08-02-05-05.xml', 'xml delta dinge');
        $remoteFS->put('delta/2018-08-07-15-17.xml', 'xml delta existing');

        $importFileScanner = new ImportFileScanner(new NullLogger(), $localFS, $remoteFS);
        $importFileScanner->setBaseFileFolder('base');
        $importFileScanner->setDeltaFileFolder('delta');
        $importFiles = $importFileScanner->scan();

        static::assertTrue(is_iterable($importFiles), 'The result not iterable');
        static::assertCount(4, $importFiles);

        $expectedTypes = ['base', 'delta', 'base', 'delta'];
        $actualTypes = collect($importFiles)->pluck('type')->all();
        static::assertEquals($expectedTypes, $actualTypes);

        $expectedOriginalFilenames = [
            '2018-08-02-03-25.xml', '2018-08-02-05-05.xml',
            '2018-08-07-15-15.xml', '2018-08-07-15-17.xml',
        ];
        $actualOriginalFilenames = collect($importFiles)->pluck('original_filename')->all();
        static::assertEquals($expectedOriginalFilenames, $actualOriginalFilenames);
    }
}