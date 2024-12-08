<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Enumerable;

class ModelColorTest implements ModelColorDTO
{
    protected ModelDTO $baseModel;
    protected array $data;
    protected ?array $sizeVariations = null;

    public function __construct(ModelDTO $baseModel, array $data)
    {
        $this->baseModel = $baseModel;
        $this->data = $data;
    }

    public function getMainArticleNumber(): string
    {
        return $this->getModelNumber() . $this->getColorNumber();
    }

    public function getColorNumber(): string
    {
        return $this->data['colorNumber'];
    }

    public function getColorName(): string
    {
        return $this->data['colorName'];
    }

    public function getSizeVariations(): Enumerable
    {
        if (!$this->sizeVariations) {
            $this->sizeVariations = collect($this->data['sizeVariations'])
                ->map(fn(array $sizeVariation): ModelColorSizeTest => new ModelColorSizeTest($this, $sizeVariation))
                ->values()
                ->toArray();
        }

        return collect($this->sizeVariations);
    }

    public function getModelName(): string
    {
        return $this->baseModel->getModelName();
    }

    public function getModelNumber(): string
    {
        return $this->baseModel->getModelNumber();
    }

    public function getVatPercentage(): float
    {
        return $this->baseModel->getVatPercentage();
    }

    public function getManufacturerName(): string
    {
        return $this->baseModel->getManufacturerName();
    }

    public function getTargetGroupGender(): ?TargetGroupGender
    {
        return $this->baseModel->getTargetGroupGender();
    }

    public function getBranches(): Enumerable
    {
        return $this->baseModel->getBranches();
    }

    public function getColorVariations(): Enumerable
    {
        return $this->baseModel->getColorVariations();
    }

    public function getImportFile(): ImportFile
    {
        return $this->baseModel->getImportFile();
    }

    public function getCurrencyIsoCode(): string
    {
        return $this->baseModel->getCurrencyIsoCode();
    }
}
