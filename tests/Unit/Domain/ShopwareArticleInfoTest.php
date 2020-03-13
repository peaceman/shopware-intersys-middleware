<?php
/**
 * lel since 30.10.18
 */
namespace Tests\Unit\Domain;

use App\Domain\Import\ShopwareArticleInfo;
use Illuminate\Support\Arr;
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

    public function testAvailabilityInfo()
    {
        $articleJSON = file_get_contents(base_path('docs/fixtures/article-with-existing-availability-response.json'));
        $articleData = json_decode($articleJSON, true);
        $availabilityKey = 'data.details.0.attribute.availability';
        $swArticleNumber = '10003436HP2900004';

        $fetchAvailInfo = function($articleData) use ($swArticleNumber) {
            $articleInfo = new ShopwareArticleInfo($articleData);
            return $articleInfo->getAvailabilityInfo($swArticleNumber);
        };

        // regular valid
        static::assertEquals([['branchNo' => '007', 'stock' => 23]], $fetchAvailInfo($articleData));

        // non existing
        Arr::forget($articleData, $availabilityKey);
        static::assertEquals([], $fetchAvailInfo($articleData));

        // empty string
        Arr::set($articleData, $availabilityKey, '');
        static::assertEquals([], $fetchAvailInfo($articleData));

        // syntactically invalid json
        Arr::set($articleData, $availabilityKey, '[{"branchNo: "007", "stock": 0}]');
        static::assertEquals([], $fetchAvailInfo($articleData));

        // invalid structure
        Arr::set($articleData, $availabilityKey, '{"branchNo": "007", "stock": 0}');
        static::assertEquals([], $fetchAvailInfo($articleData));
    }
}
