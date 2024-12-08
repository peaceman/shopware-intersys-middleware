<?php

namespace App\Domain\Import\PropertyGroup;

use Illuminate\Support\Enumerable;

interface PropertyGroupDTO
{
    public function getId(): string;
    public function getName(): string;
    public function getOptions(): Enumerable;

    public function getOptionByName(string $name): ?PropertyGroupOptionDTO;
}
