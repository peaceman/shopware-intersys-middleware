<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScanImportFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'is:scan-import-files {--skip}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan the FTP for new files to import';

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
        if ($this->option('skip')) {
            dispatch_now(new \App\Jobs\ScanImportFilesToSkip());
        } else {
            dispatch_now(new \App\Jobs\ScanImportFiles());
        }
    }
}
