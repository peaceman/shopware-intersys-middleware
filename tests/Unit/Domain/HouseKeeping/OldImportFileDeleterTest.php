<?php
/**
 * lel since 2019-07-15
 */

namespace Tests\Unit\Domain\HouseKeeping;

use App\Article;
use App\ArticleImport;
use App\Domain\HouseKeeping\OldImportFileDeleter;
use App\Domain\HouseKeeping\OldImportFileProvider;
use App\ImportFile;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OldImportFileDeleterTest extends TestCase
{
    use DatabaseMigrations;

    /** @var Filesystem */
    protected $localFS;

    /** @var OldImportFileDeleter */
    protected $deleter;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->localFS = Storage::disk('local');

        $this->deleter = $this->app->make(OldImportFileDeleter::class, [
            'localFS' => $this->localFS,
        ]);
    }

    public function testDeletesProvidedImportFiles()
    {
        // prepare import files
        $importFiles = factory(ImportFile::class, 2)->create();
        $secondImportFile = $importFiles->last();

        $storagePaths = collect($importFiles)->pluck('storage_path');

        foreach ($storagePaths as $storagePath) {
            $this->localFS->put($storagePath, 'foo');
        }

        /** @var Article $article */
        $article = factory(Article::class)->create();
        $article->imports()->save(new ArticleImport(['import_file_id' => $secondImportFile->id]));

        // prepare import file provider
        $provider = $this->getMockBuilder(OldImportFileProvider::class)
            ->setMethods(['provide'])
            ->getMock();

        $provider->expects(static::once())
            ->method('provide')
            ->willReturn($importFiles);

        // exec deleter
        $this->deleter->deleteFiles($provider);

        // assert
        /** @var ImportFile $importFile */
        foreach ($importFiles as $importFile) {
            $importFile = $importFile->refresh();
            static::assertTrue($importFile->exists);
            static::assertNull($importFile->storage_path);
        }

        foreach ($storagePaths as $storagePath) {
            static::assertFalse($this->localFS->exists($storagePath));
        }
    }
}
