<?php

namespace App\Domain\Import\Manufacturer;

interface ManufacturerImporter
{
    public function import(string $manufacturerName): ManufacturerDTO;
}
