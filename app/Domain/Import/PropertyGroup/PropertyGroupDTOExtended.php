<?php

namespace App\Domain\Import\PropertyGroup;

use Illuminate\Support\Enumerable;

class PropertyGroupDTOExtended implements PropertyGroupDTO
{
    private PropertyGroupDTO $base;
    private Enumerable $newOptions;

    public function __construct(PropertyGroupDTO $base, Enumerable $newOptions)
    {
        $this->base = $base;
        $this->newOptions = $newOptions;
    }

    public function getId(): string
    {
        return $this->base->getId();
    }

    public function getName(): string
    {
        return $this->base->getName();
    }

    public function getOptions(): Enumerable
    {
        return $this->base->getOptions()->concat($this->newOptions);
    }
}
