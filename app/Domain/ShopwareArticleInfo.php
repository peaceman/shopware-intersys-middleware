<?php
/**
 * lel since 30.10.18
 */

namespace App\Domain;


class ShopwareArticleInfo
{
    /**
     * @var array
     */
    protected $articleData;

    /**
     * ShopwareArticleInfo constructor.
     * @param array $articleData
     */
    public function __construct(string $articleData)
    {
        $this->articleData = json_decode($articleData, true);
    }

    public function isPriceProtected(string $swArticleNumber): bool
    {
        return $this->isFullyPriceProtected()
            ? true
            : $this->isPartiallyPriceProtected($swArticleNumber);
    }

    protected function isFullyPriceProtected(): bool
    {
        return $this->isMainDetailPriceProtected();
    }

    protected function isMainDetailPriceProtected(): bool
    {
        $attrValue = data_get($this->articleData, 'data.mainDetail.attribute.attr4');

        return $this->evaluateAttrValueForPriceProtection($attrValue);
    }

    protected function evaluateAttrValueForPriceProtection($attrValue): bool
    {
        if (is_null($attrValue)) return false;
        if (!is_numeric($attrValue)) return false;

        return intval($attrValue) === 1;
    }

    protected function isPartiallyPriceProtected(string $swArticleNumber): bool
    {
        if ($this->isSWArticleNumberOfMainDetail($swArticleNumber))
            return $this->isMainDetailPriceProtected();

        $variants = collect(data_get($this->articleData, 'data.details', []));
        $variant = $variants->first(function ($variant) use ($swArticleNumber) {
            return ($variant['number'] ?? '') === $swArticleNumber;
        });

        if (!$variant) return false;

        $attrValue = data_get($variant, 'attribute.attr4');
        return $this->evaluateAttrValueForPriceProtection($attrValue);
    }

    protected function isSWArticleNumberOfMainDetail(string $swArticleNumber): bool
    {
        return data_get($this->articleData, 'data.mainDetail.number') === $swArticleNumber;
    }
}
