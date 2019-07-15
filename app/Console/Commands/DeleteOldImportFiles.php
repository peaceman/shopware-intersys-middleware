<?php
/**
 * lel since 2019-07-15
 */

namespace App\Console\Commands;

use App\Domain\HouseKeeping\OldImportFileDeleter;
use App\Domain\HouseKeeping\OldImportFileProvider;
use Illuminate\Console\Command;

class DeleteOldImportFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'is:delete-old-import-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old import files';

    public function handle(OldImportFileDeleter $importFileDeleter, OldImportFileProvider $importFileProvider)
    {
        $importFileDeleter->deleteFiles($importFileProvider);
    }
}
