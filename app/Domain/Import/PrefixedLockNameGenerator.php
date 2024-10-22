<?php

namespace App\Domain\Import;

class PrefixedLockNameGenerator implements LockNameGenerator
{
    private string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    public function __invoke(string $name): string
    {
        return "{$this->prefix}-{$name}";
    }
}
