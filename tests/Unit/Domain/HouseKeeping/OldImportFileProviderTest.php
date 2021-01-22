<?php
/**
 * lel since 2019-07-15
 */

namespace Tests\Unit\Domain\HouseKeeping;

use App\Domain\HouseKeeping\OldImportFileProvider;
use App\ImportFile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class OldImportFileProviderTest extends TestCase
{
    use DatabaseMigrations;

    /** @var OldImportFileProvider */
    protected $importFileProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importFileProvider = new OldImportFileProvider();
        $this->importFileProvider->setKeepDurationInDays(3);
    }

    public function testKeepDurationNotExceeded()
    {
        $importFile = ImportFile::factory()->create([
            'created_at' => now()->subDays(1),
        ]);

        static::assertEquals(0, iterator_count($this->importFileProvider->provide()));
    }

    public function testKeepDurationExceeded()
    {
        $importFile = ImportFile::factory()->create([
            'created_at' => now()->subDays(4),
        ]);

        $providedImportFiles = iterator_to_array($this->importFileProvider->provide());
        static::assertCount(1, $providedImportFiles);

        [$providedImportFile] = $providedImportFiles;
        static::assertEquals($importFile->id, $providedImportFile->id);
    }
}
