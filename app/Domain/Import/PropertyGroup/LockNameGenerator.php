<?php

namespace App\Domain\Import\PropertyGroup;

interface LockNameGenerator
{
    public function __invoke(string $propertyGroupName): string;
}
