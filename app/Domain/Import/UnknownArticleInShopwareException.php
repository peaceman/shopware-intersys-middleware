<?php

namespace App\Domain\Import;

use App\Article;

class UnknownArticleInShopwareException extends \RuntimeException
{
    protected Article $article;

    public function __construct(Article $article)
    {
        $this->article = $article;

        $articleNumber = $article->is_modno;
        $swArticleId = $article->id;

        parent::__construct("Couldn't find ${articleNumber} as ${swArticleId} in shopware");
    }

    public function getArticle(): Article
    {
        return $this->article;
    }
}
