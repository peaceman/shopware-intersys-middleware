<?php
/**
 * lel since 30.10.18
 */

namespace App\Domain\Import;


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
    public function __construct(array $articleData)
    {
        $this->articleData = $articleData;
    }

    public function getMainDetailArticleId(): int
    {
        return data_get($this->articleData, 'data.id');
    }

    public function isPriceProtected(string $swArticleNumber): bool
    {
        return $this->isFullyPriceProtected()
            ? true
            : $this->isPartiallyPriceProtected($swArticleNumber);
    }

    public function variantExists(string $swArticleNumber): bool
    {
        return collect(data_get($this->articleData, 'data.details', []))
                ->where('number', $swArticleNumber)
                ->first() !== null;
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
