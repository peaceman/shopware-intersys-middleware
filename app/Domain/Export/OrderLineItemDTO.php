<?php

namespace App\Domain\Export;

interface OrderLineItemDTO
{
    public function getEan(): ?string;
    public function getQuantity(): int;
    public function getPrice(): float;
    public function getUnitPrice(): float;
    public function getProductNumber(): ?string;
    public function isProduct(): bool;
}
