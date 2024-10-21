<?php

namespace App\Domain\Import\PropertyGroup;

class PropertyGroupOptionDTO
{
    private array $data;

    public function __construct(array $data)
    {
        return $this->data = $data;
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getId(): string
    {
        return $this->data['id'];
    }
}
