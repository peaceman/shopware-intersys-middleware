<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ModelColorSizeXML implements ModelColorSizeDTO
{
    private ModelColorXML $baseModel;
    private \SimpleXMLElement $sizeNode;
    private ?\SimpleXMLElement $branchNode = null;

    public function __construct(
        ModelColorXML $baseModel,
        \SimpleXMLElement $sizeNode,
    ) {
        $this->baseModel = $baseModel;
        $this->sizeNode = $sizeNode;
    }

    // ModelDTO interface implementations

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
        return Collection::make($this->sizeNode->xpath('Branch') ?: [])
            ->map(fn (\SimpleXMLElement $e): string => (string) $e->Branchno)
            ->values();
    }

    public function getColorVariations(): Enumerable
    {
        return $this->baseModel->getColorVariations();
    }

    public function getImportFile(): ImportFile
    {
        return $this->baseModel->getImportFile();
    }

    // ModelColorDTO interface implementations

    public function getMainArticleNumber(): string
    {
        return $this->baseModel->getMainArticleNumber();
    }

    public function getColorNumber(): string
    {
        return $this->baseModel->getColorNumber();
    }

    public function getColorName(): string
    {
        return $this->baseModel->getColorName();
    }

    public function getSizeVariations(): Enumerable
    {
        return $this->baseModel->getSizeVariations();
    }

    // ModelColorSizeDTO interface implementations

    public function getVariantArticleNumber(): string
    {
        return (string) $this->sizeNode->Itemno;
    }

    public function getSize(): string
    {
        return (string) $this->sizeNode->Sizedeno;
    }

    public function getEan(): string
    {
        return (string) $this->sizeNode->Ean;
    }

    public function getVariantName(): string
    {
        return (string) $this->sizeNode->Itemdeno;
    }

    public function getPrice(): float
    {
        return floatval((string) $this->getBranchNode()->Saleprice);
    }

    public function getPseudoPrice(): ?float
    {
        if (is_null($v = $this->getBranchNode()->Xprice ?? null))
            return null;

        return floatval((string) $v);
    }

    public function getStockPerBranch(): Enumerable
    {
        return Collection::make($this->sizeNode->xpath('Branch') ?: [])
            ->mapWithKeys(fn (\SimpleXMLElement $e): array => [(string) $e->Branchno => (int) $e->Stockqty]);
    }

    private function getBranchNode(): \SimpleXMLElement
    {
        if (!$this->branchNode) {
            $this->branchNode = $this->sizeNode->xpath('Branch')[0];
        }

        return $this->branchNode;
    }
}
