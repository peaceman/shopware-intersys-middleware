<?php
/**
 * lel since 13.03.20
 */

namespace App\Commands;

use App\Domain\ArticleDeactivation\AbandonedArticleIDProvider;
use App\Domain\ArticleDeactivation\ArticleDeactivator;

class DeactivateArticles
{
    /**
     * @var ArticleDeactivator
     */
    private $articleDeactivator;
    /**
     * @var AbandonedArticleIDProvider
     */
    private $abandonedArticleIDProvider;

    public function __construct(
        ArticleDeactivator $articleDeactivator,
        AbandonedArticleIDProvider $abandonedArticleIDProvider
    ) {

        $this->articleDeactivator = $articleDeactivator;
        $this->abandonedArticleIDProvider = $abandonedArticleIDProvider;
    }

    public function __invoke(): void
    {
        $this->articleDeactivator->deactivate($this->abandonedArticleIDProvider);
    }
}
