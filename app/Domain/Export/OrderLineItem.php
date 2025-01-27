<?php

namespace App\Domain\Export;

class OrderLineItem implements OrderLineItemDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getEan(): ?string
    {
        return $this->data['product']['ean'] ?? null;
    }

    public function getQuantity(): int
    {
        return $this->data['quantity'];
    }

    public function getPrice(): float
    {
        return $this->data['price']['totalPrice'];
    }

    public function getUnitPrice(): float
    {
        return $this->data['price']['unitPrice'];
    }

    public function getProductNumber(): ?string
    {
        return $this->data['product']['productNumber'] ?? null;
    }

    public function isProduct(): bool
    {
        return $this->data['type'] === 'product';
    }
}
