<?php

namespace App\Domain\Import\PropertyGroup;

interface OptionPositionAdvisor
{
    public function __invoke(string $optionName): int;
}
