<?php

namespace App\Domain\Import\PropertyGroup;

class OptionPositionAdvisorFixed implements OptionPositionAdvisor
{
    private int $position;

    public function __construct(int $position)
    {
        $this->position = $position;
    }

    public function __invoke(string $optionName): int
    {
        return $this->position;
    }
}
