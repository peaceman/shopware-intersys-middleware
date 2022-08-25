<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ModelColorXML implements ModelColorDTO
{
    private ModelXML $baseModel;
    private \SimpleXMLElement $colorNode;

    public function __construct(
        ModelXML $baseModel,
        \SimpleXMLElement $colorNode,
    ) {
        $this->baseModel = $baseModel;
        $this->colorNode = $colorNode;
    }

    public function getImportFile(): ImportFile
    {
        return $this->baseModel->getImportFile();
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
        return Collection::make($this->colorNode->xpath('Size/Branch') ?: [])
            ->map(fn (\SimpleXMLElement $e): string => (string) $e->Branchno)
            ->values();
    }

    public function getColorNumber(): string
    {
        return (string) $this->colorNode->Colno;
    }

    public function getColorName(): string
    {
        return (string) $this->colorNode->Colordeno;
    }

    /**
     * @return Enumerable<array-key, ModelColorSizeXML>
     */
    public function getSizeVariations(): Enumerable
    {
        return Collection::make($this->colorNode->xpath('Size') ?: [])
            ->map(fn (\SimpleXMLElement $size): ModelColorSizeDTO => new ModelColorSizeXML($this, $size));
    }

    public function getMainArticleNumber(): string
    {
        return $this->getModelNumber() . $this->getColorNumber();
    }

    public function getColorVariations(): Enumerable
    {
        return $this->baseModel->getColorVariations();
    }
}
