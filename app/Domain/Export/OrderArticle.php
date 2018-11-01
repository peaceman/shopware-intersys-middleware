<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class OrderArticle
{
    /**
     * @var array
     */
    protected $data;

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
}
