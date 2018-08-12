<?php
/**
 * lel since 12.08.18
 */

namespace Tests\Unit\Jobs;

use App\Domain\Import\ImportFileScanner;
use App\ImportFile;
use App\Jobs\ParseBaseXML;
use App\Jobs\ParseModelXML;
use App\Jobs\ScanImportFiles;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ScanImportFilesTest extends TestCase
{
    use DatabaseMigrations;

    public function testDispatchesJobsForScannedImportFiles()
    {
        $importFiles = [
            new ImportFile(['type' => 'base']),
            new ImportFile(['type' => 'delta']),
            new ImportFile(['type' => 'delta']),
        ];

        $importFileScanner = Mockery::mock(ImportFileScanner::class);
        $importFileScanner->expects()->scan()->andReturn(collect($importFiles));

        Queue::fake();
        $scanImportFiles = new ScanImportFiles();
        $scanImportFiles->handle($importFileScanner);

        Queue::assertPushedWithChain(ParseBaseXML::class, [
            new ParseBaseXML($importFiles[1]),
            new ParseBaseXML($importFiles[2]),
        ]);
    }
}