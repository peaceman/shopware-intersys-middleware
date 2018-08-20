<?php

namespace App\Console\Commands;

use App\Domain\Import\ArticleDeactivation\AbandonedArticleIDProvider;
use App\Domain\Import\ArticleDeactivation\ArticleDeactivator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeactivateArticles extends Command
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
     * @param ArticleDeactivator $articleDeactivator
     * @param AbandonedArticleIDProvider $articleIDProvider
     * @return mixed
     */
    public function handle(ArticleDeactivator $articleDeactivator, AbandonedArticleIDProvider $articleIDProvider)
    {
        $articleDeactivator->deactivate($articleIDProvider);
    }
}
