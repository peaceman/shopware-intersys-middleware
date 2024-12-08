<?php

namespace App\Domain\Import;

use Illuminate\Support\Arr;

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

    public function isPriceProtected(string $ean): bool
    {
        $child = $this->getChildByEan($ean);

        return (bool) Arr::get($child, 'customFields.sim_protected_price', false);
    }

    public function isMissingListPrice(string $ean): bool
    {
        $child = $this->getChildByEan($ean);
        if (empty($child['price'])) return false;

        $listPrice = Arr::get($child, 'price.0.listPrice');
        return empty($listPrice);
    }
}
