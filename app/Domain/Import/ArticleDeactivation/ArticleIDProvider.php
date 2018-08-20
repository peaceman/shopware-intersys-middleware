<?php
/**
 * lel since 20.08.18
 */

namespace App\Domain\Import\ArticleDeactivation;

interface ArticleIDProvider
{
    public function getArticleIDs(): iterable;
}