<?php
/**
 * lel since 31.10.18
 */

namespace App\Domain\Import;

use App\Manufacturer;
use App\ManufacturerSizeMapping;
use Illuminate\Database\Eloquent\Collection;

class SizeMapper
{
    public function mapSize(string $manufacturerName, ?string $fedas, string $sourceSize)
    {
        if (empty($fedas)) return $sourceSize;

        $sizeMappings = $this->fetchSizeMappingsForManufacturer($manufacturerName);
        $gender = $this->getGenderFromFedas($fedas);
        if (empty($gender)) return $sourceSize;

        return data_get($sizeMappings, [$gender, strtolower(trim($sourceSize))], $sourceSize);
    }

    protected function fetchSizeMappingsForManufacturer(string $manufacturerName): array
    {
        $manufacturerName = trim($manufacturerName);

        if (!$sizeMappings = ($this->sizeMappings[$manufacturerName] ?? null)) {
            $sizeMappings = $this->loadSizeMappingsForManufacturer($manufacturerName);
        }

        return $sizeMappings;
    }

    protected function loadSizeMappingsForManufacturer(string $manufacturerName): array
    {
        /** @var Manufacturer $manufacturer */
        $manufacturer = Manufacturer::query()->where('name', $manufacturerName)->first();
        if (!$manufacturer) return [];

        $sizeMappings = $manufacturer->sizeMappings
            ->groupBy('gender')
            ->map(function (Collection $sizeMappings) {
                return $sizeMappings
                    ->mapWithKeys(function (ManufacturerSizeMapping $msm) {
                        return [strtolower(trim($msm->source_size)) => $msm->target_size];
                    })
                    ->toArray();
            })
            ->toArray();

        return $sizeMappings;
    }

    protected function getGenderFromFedas(string $fedas): ?string
    {
        $lastDigit = substr($fedas, -1, 1);

        switch (intval($lastDigit)) {
            case 1:
                return ManufacturerSizeMapping::GENDER_MALE_UNISEX;
            case 2:
                return ManufacturerSizeMapping::GENDER_FEMALE;
            case 3:
                return ManufacturerSizeMapping::GENDER_CHILD;
            case 4:
                return ManufacturerSizeMapping::GENDER_MALE_UNISEX;
            case 5:
                return ManufacturerSizeMapping::GENDER_FEMALE;
            case 6:
                return ManufacturerSizeMapping::GENDER_CHILD;
            case 7:
                return ManufacturerSizeMapping::GENDER_MALE_UNISEX;
            case 8:
                return ManufacturerSizeMapping::GENDER_FEMALE;
            case 9:
                return ManufacturerSizeMapping::GENDER_CHILD;
            default:
                return null;
        }
    }
}
