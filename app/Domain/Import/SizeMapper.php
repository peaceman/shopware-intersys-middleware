<?php
/**
 * lel since 31.10.18
 */

namespace App\Domain\Import;

use App\Manufacturer;
use App\ManufacturerSizeMapping;
use App\SizeMappingExclusion;
use Illuminate\Database\Eloquent\Collection;

class SizeMapper
{
    protected array $sizeMappings = [];

    public function mapSize(SizeMappingRequest $req)
    {
        if (empty($gender = $req->getTargetGroupGender()) || $this->isExcluded($req))
            return $req->getSize();

        $sizeMappings = $this->fetchSizeMappingsForManufacturer($req->getManufacturerName());
        if (empty($gender)) return $req->getSize();

        return data_get(
            $sizeMappings,
            [$gender->value, strtolower(trim($req->getSize()))],
            $req->getSize()
        );
    }

    protected function isExcluded(SizeMappingRequest $req): bool
    {
        return SizeMappingExclusion::query()
            ->where('article_number', $req->getMainArticleNumber())
            ->orWhere('article_number', $req->getVariantArticleNumber())
            ->exists();
    }

    protected function fetchSizeMappingsForManufacturer(string $manufacturerName): array
    {
        $manufacturerName = trim($manufacturerName);

        if (!$sizeMappings = ($this->sizeMappings[$manufacturerName] ?? null)) {
            $sizeMappings = $this->loadSizeMappingsForManufacturer($manufacturerName);
            $this->sizeMappings[$manufacturerName] = $sizeMappings;
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
}
