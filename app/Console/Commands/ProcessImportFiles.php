<?php

namespace App\Console\Commands;

use App\ImportFile;
use App\Jobs\ParseBaseXML;
use Illuminate\Console\Command;

class ProcessImportFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'is:process-import-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $importFiles = ImportFile::readyForImport()->orderBy('original_filename', 'asc')->get();

        $importFile = $importFiles->shift();
        dispatch(new ParseBaseXML($importFile))
            ->chain($importFiles->map(function (ImportFile $importFile) {
                return new ParseBaseXML($importFile);
            }));
    }
}
