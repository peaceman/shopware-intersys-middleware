<?php
/**
 * lel since 20.08.18
 */

namespace App\Domain\Import\ArticleDeactivation;


use App\Article;
use App\Domain\ShopwareAPI;
use Psr\Log\LoggerInterface;

class ArticleDeactivator
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ShopwareAPI
     */
    private $shopwareAPI;

    /**
     * ArticleDeactivator constructor.
     * @param LoggerInterface $logger
     * @param ShopwareAPI $shopwareAPI
     */
    public function __construct(LoggerInterface $logger, ShopwareAPI $shopwareAPI)
    {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
    }

    public function deactivate(ArticleIDProvider $idProvider)
    {
        foreach ($idProvider->getArticleIDs() as $articleID) {
            /** @var Article $article */
            $article = Article::findOrFail($articleID);

            $this->shopwareAPI->deactivateShopwareArticle($article->sw_article_id);

            $article->update(['is_active' => false]);

            $this->logger->info(__METHOD__ . ' Deactivated article', [
                'articles.id' => $article->id,
                'articles.sw_article_id' => $article->sw_article_id,
            ]);
        }
    }
}