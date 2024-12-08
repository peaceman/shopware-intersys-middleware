<?php

namespace App\Domain\Import\Manufacturer;

use Illuminate\Support\Str;

class ManufacturerImporterFake implements ManufacturerImporter
{
    public function import(string $manufacturerName): ManufacturerDTO
    {
        return new ManufacturerDTO(['id' => Str::random(40), 'name' => $manufacturerName]);
    }
}
