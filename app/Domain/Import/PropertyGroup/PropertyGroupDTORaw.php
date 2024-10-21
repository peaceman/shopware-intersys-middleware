<?php

namespace App\Domain\Import\PropertyGroup;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class PropertyGroupDTORaw implements PropertyGroupDTO
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

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getOptions(): Enumerable
    {
        return collect($this->data['options'] ?? [])
            ->map(fn (array $data): PropertyGroupOptionDTO => new PropertyGroupOptionDTO($data));
    }
}
