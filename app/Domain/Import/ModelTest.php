<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Enumerable;

class ModelTest implements ModelDTO
{
    protected array $data;
    protected ImportFile $importFile;
    protected ?array $colorVariations = null;

    public function __construct(ImportFile $importFile, array $data)
    {
        $this->data = $data;
        $this->importFile = $importFile;
    }

    public function getModelName(): string
    {
        return $this->data['name'];
    }

    public function getModelNumber(): string
    {
        return $this->data['number'];
    }

    public function getVatPercentage(): float
    {
        return $this->data['vatPercentage'] ?? 0.19;
    }

    public function getManufacturerName(): string
    {
        return $this->data['manufacturerName'] ?? 'poggers manufacturing company';
    }

    public function getTargetGroupGender(): ?TargetGroupGender
    {
        return $this->data['targetGroupGender'] ?? TargetGroupGender::Male;
    }

    /**
     * @inheritDoc
     */
    public function getBranches(): Enumerable
    {
        return collect($this->data['gln']);
    }

    public function getImportFile(): ImportFile
    {
        return $this->importFile;
    }

    public function getCurrencyIsoCode(): string
    {
        return $this->data['currencyIsoCode'] ?? 'EUR';
    }

    /**
     * @inheritDoc
     */
    public function getColorVariations(): Enumerable
    {
        if (!$this->colorVariations) {
            $this->colorVariations = collect($this->data['colorVariations'])
                ->map(fn(array $colorVariant): ModelColorTest => new ModelColorTest($this, $colorVariant))
                ->values()
                ->toArray();
        }

        return collect($this->colorVariations);
    }
}
