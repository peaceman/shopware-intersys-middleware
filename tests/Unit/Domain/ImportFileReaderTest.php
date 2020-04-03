<?php
/**
 * lel since 11.08.18
 */

namespace Tests\Unit\Domain;

use App\Domain\Import\ImportFileReader;
use App\Domain\Import\ModelXMLData;
use App\ImportFile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LimitIterator;
use Mockery;
use Psr\Log\NullLogger;
use Tests\TestCase;
use function GuzzleHttp\Psr7\stream_for;

class ImportFileReaderTest extends TestCase
{
    use DatabaseMigrations;

    public function testValidDataGeneratesModelXMLDataObjects()
    {
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => '2018-08-10-03-25.xml',
            'storage_path' => Str::random(40)
        ]);
        $importFile->save();

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $localFS->put($importFile->storage_path, stream_for(fopen(base_path('docs/fixtures/2018-08-10-03-25.xml'), 'r')));

        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);

        $dataObjects = iterator_to_array(new LimitIterator($baseXMLReader($importFile), 0, 3));
        static::assertCount(3, $dataObjects);
        static::assertContainsOnlyInstancesOf(ModelXMLData::class, $dataObjects);
        static::assertNull($importFile->processed_at);
    }

    public function testImportFileThatDoesntQualifyForImportDoesntGenerateAnything()
    {
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => 'lel.xml',
        ]);

        $importFile = Mockery::mock($importFile);
        $importFile->expects()->qualifiesForImport()->andReturn(false);

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);

        $dataObjects = iterator_to_array($baseXMLReader($importFile));

        static::assertEmpty($dataObjects);
    }

    public function testImportFileIsMarkedAsProcessed()
    {
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => '2018-08-10-03-25.xml',
            'storage_path' => Str::random(40)
        ]);
        $importFile->save();

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $localFS->put($importFile->storage_path, stream_for(fopen(base_path('docs/fixtures/2018-08-10-03-25.xml'), 'r')));

        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);
        $iter = $baseXMLReader($importFile);

        while ($iter->valid()) $iter->next();

        static::assertNotNull($importFile->processed_at);
    }
}
