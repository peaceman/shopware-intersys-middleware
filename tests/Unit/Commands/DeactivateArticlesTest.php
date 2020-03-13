<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Commands;

use App\Commands\DeactivateArticles;
use App\Domain\ArticleDeactivation\AbandonedArticleIDProvider;
use App\Domain\ArticleDeactivation\ArticleDeactivator;
use Tests\TestCase;

class DeactivateArticlesTest extends TestCase
{
    public function testExecution(): void
    {
        $articleDeactivator = $this->createMock(ArticleDeactivator::class);
        $abandonedArticleIDProvider = $this->createMock(AbandonedArticleIDProvider::class);

        $articleDeactivator->expects(static::once())->method('deactivate')
            ->with($abandonedArticleIDProvider);

        $deactivateArticles = $this->app->make(DeactivateArticles::class, [
            'articleDeactivator' => $articleDeactivator,
            'abandonedArticleIDProvider' => $abandonedArticleIDProvider,
        ]);

        $deactivateArticles();
    }
}
