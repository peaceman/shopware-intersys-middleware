<?php

namespace App\Console\Commands;

use App\Commands\ExportOrders;
use Illuminate\Console\Command;

class ExportOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:export-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(ExportOrders $exportOrders)
    {
        $exportOrders();
    }
}
