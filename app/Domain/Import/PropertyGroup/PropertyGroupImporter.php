<?php

namespace App\Domain\Import\PropertyGroup;

interface PropertyGroupImporter
{
    public function import(string $groupName, array $optionNames): PropertyGroupDTO;
}
