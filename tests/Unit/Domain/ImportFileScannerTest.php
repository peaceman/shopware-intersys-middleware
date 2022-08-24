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

        $remoteFS->put('outoforder.csv', 'no');
        $remoteFS->put('stock/Base-lel.csv', 'xml base dinge');
        $remoteFS->put('stock/Base-lel2.csv', 'xml base dinge 2');
        $remoteFS->put('stock/Base-existing.csv', 'xml base existing');
        $remoteFS->put('stock/Delta-lel.csv', 'xml delta dinge');
        $remoteFS->put('stock/Delta-existing.csv', 'xml delta existing');
        (new ImportFile(['type' => 'base', 'original_filename' => 'Base-existing.csv', 'storage_path' => 'meh']))->save();
        (new ImportFile(['type' => 'delta', 'original_filename' => 'Delta-existing.csv', 'storage_path' => 'meh']))->save();

        $importFileScanner = new ImportFileScanner(new NullLogger(), $localFS, $remoteFS);
        $importFileScanner->setFolder('stock');
        $importFiles = $importFileScanner->scan();

        static::assertTrue(is_iterable($importFiles), 'The result not iterable');
        static::assertCount(3, $importFiles);

        $expectedTypes = ['base', 'base', 'delta'];
        $actualTypes = collect($importFiles)->pluck('type')->sort()->values()->all();
        static::assertEquals($expectedTypes, $actualTypes);

        $expectedOriginalFilenames = ['Base-lel.csv', 'Delta-lel.csv', 'Base-lel2.csv'];
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

        $remoteFS->put('outoforder.csv', 'no');
        $remoteFS->put('stock/Base-100343-20220728145454', 'xml base dinge');
        $remoteFS->put('stock/Base-100343-20220729145454', 'xml base dinge 2');
        $remoteFS->put('stock/Delta-100343-20220728175454-1', 'xml delta dinge');
        $remoteFS->put('stock/Delta-100343-20220729175454-2', 'xml delta existing');

        $importFileScanner = new ImportFileScanner(new NullLogger(), $localFS, $remoteFS);
        $importFileScanner->setFolder('stock');
        $importFiles = $importFileScanner->scan();

        static::assertTrue(is_iterable($importFiles), 'The result not iterable');
        static::assertCount(4, $importFiles);

        $expectedTypes = ['base', 'delta', 'base', 'delta'];
        $actualTypes = collect($importFiles)->pluck('type')->all();
        static::assertEquals($expectedTypes, $actualTypes);

        $expectedOriginalFilenames = [
            'Base-100343-20220728145454', 'Delta-100343-20220728175454-1',
            'Base-100343-20220729145454', 'Delta-100343-20220729175454-2',
        ];
        $actualOriginalFilenames = collect($importFiles)->pluck('original_filename')->all();
        static::assertEquals($expectedOriginalFilenames, $actualOriginalFilenames);
    }
}
