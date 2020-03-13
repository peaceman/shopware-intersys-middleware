<?php

namespace App\Console\Commands;

use App\Commands\DeactivateArticles;
use Illuminate\Console\Command;

class DeactivateArticlesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:deactivate-articles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate articles in shopware that were not in the base xml files for more then 7 days';

    public function handle(DeactivateArticles $deactivateArticles)
    {
        $deactivateArticles();
    }
}
