<?php

namespace App\Domain\Import\PropertyGroup;

use Illuminate\Support\Str;

class LockNameGeneratorAsciiGroupName implements LockNameGenerator
{
    public function __invoke(string $propertyGroupName): string
    {
        return 'property-group-import-' . Str::lower(Str::ascii($propertyGroupName));
    }
}
