<?php

namespace App\Domain\Import\PropertyGroup;

use App\Domain\Import\PrefixedLockNameGenerator;
use Illuminate\Support\Str;

class LockNameGeneratorAsciiGroupName extends PrefixedLockNameGenerator
{
    public function __construct()
    {
        parent::__construct('property-group-import');
    }

    public function __invoke(string $propertyGroupName): string
    {
        return parent::__invoke(Str::lower(Str::ascii($propertyGroupName)));
    }
}
