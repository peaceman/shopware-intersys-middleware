<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;

class ModelColorSizeTest implements ModelColorSizeDTO
{
    protected ModelColorDTO $baseModel;
    protected array $data;

    public function __construct(ModelColorDTO $baseModel, array $data)
    {
        $this->baseModel = $baseModel;
        $this->data = array_merge(
            [
                'price' => 23.5,
                'ean' => Str::random(14),
                'articleNumber' => implode('-', [$baseModel->getMainArticleNumber(), Str::random(4)]),
                'stock' => 0,
            ],
            $data,
        );
    }

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

    public function getVariantArticleNumber(): string
    {
        return $this->data['articleNumber'];
    }

    public function getSize(): string
    {
        return $this->data['size'];
    }

    public function getEan(): string
    {
        return $this->data['ean'];
    }

    public function getVariantName(): string
    {
        return $this->data['name'] ?? 'dis is name';
    }

    public function getPrice(): float
    {
        return $this->data['price'];
    }

    public function getNetPrice(): float
    {
        return $this->data['price'] / (1 + ($this->getVatPercentage() / 100));
    }

    public function getPseudoPrice(): ?float
    {
        return null;
    }

    public function getStockPerBranch(): Enumerable
    {
        return collect([$this->getBranches()->first() => $this->data['stock']]);
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
