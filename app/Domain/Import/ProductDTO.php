<?php

namespace App\Domain\Import;

use Illuminate\Support\Arr;
use JetBrains\PhpStorm\Deprecated;

class ProductDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return $this->data['id'];
    }

    public function getChildren(): array
    {
        return $this->data['children'] ?? [];
    }

    public function getConfiguratorSettings(): array
    {
        return $this->data['configuratorSettings'] ?? [];
    }

    public function getChildByEan(string $ean): ?array
    {
        return collect($this->getChildren())
            ->firstWhere('ean', $ean);
    }

    public function isPriceProtected(): bool
    {
        return (bool) Arr::get($this->data, 'customFields.sim_protected_price', false);
    }

    public function isMissingListPrice(): bool
    {
        $price = $this->getPrice();
        if (empty($price)) return true;

        return empty($price['listPrice']);
    }

    public function getPrice(): ?array
    {
        $prices = $this->data['price'] ?? [];
        if (empty($prices)) return null;

        return head($prices);
    }
}
