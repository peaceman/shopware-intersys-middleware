<?php

namespace App\Domain\Import;

use App\Domain\Import\LockNameGenerator;

class LockNameGeneratorRaw implements LockNameGenerator
{
    public function __invoke(string $name): string
    {
        return $name;
    }
}
