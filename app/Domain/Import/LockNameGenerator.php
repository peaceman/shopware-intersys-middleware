<?php

namespace App\Domain\Import;

interface LockNameGenerator
{
    public function __invoke(string $name): string;
}
