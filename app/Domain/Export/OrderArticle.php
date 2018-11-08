<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class OrderArticle
{
    const MODE_PRODUCT = 0;
    const MODE_VOUCHER = 2;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var float
     */
    protected $voucherPercentage = 0.0;

    /**
     * OrderArticle constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getArticleNumber(): string
    {
        return $this->data['articleNumber'];
    }

    public function getPrice(): float
    {
        return $this->data['price'];
    }

    public function getQuantity(): int
    {
        return $this->data['quantity'];
    }

    public function getFullPrice(): float
    {
        return $this->getPrice() * $this->getQuantity() * (1 - $this->getVoucherPercentage());
    }

    public function getPositionID(): int
    {
        return $this->data['id'];
    }

    public function getPositionStatusID(): int
    {
        return $this->data['statusId'];
    }

    /**
     * @return float
     */
    public function getVoucherPercentage(): float
    {
        return $this->voucherPercentage;
    }

    /**
     * @param float $voucherPercentage
     * @return OrderArticle
     */
    public function setVoucherPercentage(float $voucherPercentage): OrderArticle
    {
        $this->voucherPercentage = $voucherPercentage;

        return $this;
    }

    public function isVoucher()
    {
        return $this->data['mode'] === static::MODE_VOUCHER;
    }

    public function getArticleName(): string
    {
        return $this->data['articleName'];
    }
}
