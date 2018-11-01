<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class Order
{
    protected $data;
    protected $articles = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getID(): int
    {
        return $this->data['id'];
    }

    public function getOrderTime(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $this->data['orderTime']);
    }

    public function getOrderNumber(): string
    {
        return $this->data['number'];
    }

    /**
     * @param OrderArticle[] $articles
     */
    public function setArticles(array $articles): void
    {
        $this->articles = $articles;
    }

    /**
     * @return OrderArticle[]
     */
    public function getArticles(): array
    {
        return $this->articles;
    }
}
