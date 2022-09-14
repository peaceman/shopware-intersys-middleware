<?php
/**
 * lel since 11.08.18
 */

namespace Tests\Unit\Domain;

use App\Domain\Import\ImportFileReader;
use App\Domain\Import\ModelColorDTO;
use App\Domain\Import\ModelColorSizeDTO;
use App\Domain\Import\ModelCSV;
use App\Domain\Import\ModelDTO;
use App\Domain\Import\ModelXML;
use App\Domain\Import\TargetGroupGender;
use App\ImportFile;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LimitIterator;
use Mockery;
use Psr\Log\NullLogger;
use Tests\TestCase;

class ImportFileReaderTest extends TestCase
{
    use DatabaseMigrations;

    public function generatesImportFilesProvider(): iterable
    {
        yield [
            '2018-08-10-03-25.xml',
            ModelXML::class,
        ];

        yield [
            'Base4WebShop-100343-20220822114000.csv',
            ModelCSV::class,
        ];
    }

    /**
     * @dataProvider generatesImportFilesProvider
     */
    public function testValidDataGeneratesModelDataObjects(
        string $filename,
        string $modelDataClass,
    ) {
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => $filename,
            'storage_path' => Str::random(40)
        ]);
        $importFile->save();

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $localFS->put($importFile->storage_path, Utils::streamFor(fopen(base_path("docs/fixtures/{$filename}"), 'r')));

        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);

        $dataObjects = iterator_to_array(new LimitIterator($baseXMLReader($importFile), 0, 3));
        static::assertCount(3, $dataObjects);
        static::assertContainsOnlyInstancesOf($modelDataClass, $dataObjects);
        static::assertNull($importFile->processed_at);
    }

    /**
     * @dataProvider generatesImportFilesProvider
     */
    public function testImportFileThatDoesntQualifyForImportDoesntGenerateAnything(
        string $filename,
    ) {
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => $filename,
        ]);

        $importFile = Mockery::mock($importFile);
        $importFile->expects()->qualifiesForImport()->andReturn(false);

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);

        $dataObjects = iterator_to_array($baseXMLReader($importFile));

        static::assertEmpty($dataObjects);
    }

    /**
     * @dataProvider generatesImportFilesProvider
     */
    public function testImportFileIsMarkedAsProcessed(
        string $filename,
    ) {
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => $filename,
            'storage_path' => Str::random(40)
        ]);
        $importFile->save();

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $localFS->put(
            $importFile->storage_path,
            Utils::streamFor(fopen(base_path("docs/fixtures/{$filename}"), 'r')),
        );

        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);
        $iter = $baseXMLReader($importFile);

        while ($iter->valid()) $iter->next();

        static::assertNotNull($importFile->processed_at);
    }

    public function testCSVRecords(): void
    {
        $filename = 'Base4WebShop-100343-20220822114000.csv';
        $importFile = new ImportFile([
            'type' => 'base',
            'original_filename' => $filename,
            'storage_path' => Str::random(40)
        ]);
        $importFile->save();

        Storage::fake('local');
        $localFS = Storage::disk('local');
        $localFS->put($importFile->storage_path, Utils::streamFor(fopen(base_path("docs/fixtures/{$filename}"), 'r')));

        $baseXMLReader = new ImportFileReader(new NullLogger(), $localFS);

        $dataObjects = iterator_to_array($baseXMLReader($importFile));
        static::assertCount(5, $dataObjects);

        /** @var Collection<string, ModelDTO> $models */
        $models = Collection::make($dataObjects)
            ->keyBy(fn (ModelDTO $model): string => $model->getModelNumber());

        $model = $models->get('000995');
        static::assertNotNull($model->getImportFile());
        static::assertEquals('B REN SHORT', $model->getModelName());
        static::assertEquals(19, $model->getVatPercentage());
        static::assertEquals('ARENA', $model->getManufacturerName());
        static::assertEquals(TargetGroupGender::Child, $model->getTargetGroupGender());
        static::assertEquals(['4399902245876', '4399901690509'], $model->getBranches()->toArray());

        $colorVariations = $model->getColorVariations();
        static::assertCount(1, $colorVariations);

        /** @var ModelColorDTO $colorVariation */
        [$colorVariation] = $colorVariations;

        static::assertEquals('000995508', $colorVariation->getMainArticleNumber());
        static::assertEquals('508', $colorVariation->getColorNumber());
        static::assertEquals('BLACK-PIX BLUE-TURQU', $colorVariation->getColorName());

        $sizeVariations = $colorVariation->getSizeVariations();
        static::assertCount(4, $sizeVariations);

        /** @var ModelColorSizeDTO $sizeVariation */
        $sizeVariation = $sizeVariations
            ->first(fn (ModelColorSizeDTO $sizeVariation): bool => $sizeVariation->getSize() === '164');

        static::assertEquals(['4399901690509', '4399902245876'], $sizeVariation->getBranches()->toArray());

        $stockPerBranch = $sizeVariation->getStockPerBranch();
        static::assertEquals(2, $stockPerBranch->get('4399901690509'));
        static::assertEquals(1, $stockPerBranch->get('4399902245876'));

        static::assertEquals('0009951525082382', $sizeVariation->getVariantArticleNumber());
        static::assertEquals('3468335974835', $sizeVariation->getEan());
        static::assertEquals(22.99, $sizeVariation->getPrice());
        static::assertNull($sizeVariation->getPseudoPrice());
        static::assertEquals('B REN SHORT BLACK-PIX BLUE-TURQU 164', $sizeVariation->getVariantName());
    }
}
