<?php

namespace App\Domain\Import\PropertyGroup;

class LockNameGeneratorRaw implements LockNameGenerator
{
    public function __invoke(string $propertyGroupName): string
    {
        return $propertyGroupName;
    }
}
