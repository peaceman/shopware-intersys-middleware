<?php
/**
 * lel since 30.10.18
 */
namespace Tests\Unit\Domain;

use App\Domain\ShopwareArticleInfo;
use Tests\TestCase;

class ShopwareArticleInfoTest extends TestCase
{
    public function testFullPriceProtectedArticle()
    {
        $articleJSON = file_get_contents(base_path('docs/fixtures/full-price-protected-article-response.json'));
        $articleInfo = new ShopwareArticleInfo(json_decode($articleJSON, true));

        static::assertEquals(true, $articleInfo->isPriceProtected('10003436H000'));
        static::assertEquals(true, $articleInfo->isPriceProtected('10003436HP2900004'));
        static::assertEquals(true, $articleInfo->isPriceProtected('W515C17LOVHH0500038'));
    }

    public function testPartialPriceProtectedArticle()
    {
        $articleJSON = file_get_contents(base_path('docs/fixtures/partial-price-protected-article-response.json'));
        $articleInfo = new ShopwareArticleInfo(json_decode($articleJSON, true));

        static::assertEquals(false, $articleInfo->isPriceProtected('10003436H004'));
        static::assertEquals(false, $articleInfo->isPriceProtected('10003436HP2900413'));
        static::assertEquals(true, $articleInfo->isPriceProtected('10003436HP2900417'));
    }
}
