<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ModelColorSizeCSV implements ModelColorSizeDTO
{
    private ModelColorCSV $baseModel;
    private array $records;
    private array $rec;

    public function __construct(
        ModelColorCSV $baseModel,
        array $records,
    ) {
        if (empty($records))
            throw new \InvalidArgumentException("ModelColorSizeCSV records can not be empty");

        $this->baseModel = $baseModel;
        $this->records = $records;
        [$this->rec] = $records;
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

    /**
     * @inheritDoc
     */
    public function getSizeVariations(): Enumerable
    {
        return $this->baseModel->getSizeVariations();
    }

    public function getVariantArticleNumber(): string
    {
        return $this->getModelNumber()
            . $this->rec['MARKENNR']
            . $this->getColorNumber()
            . $this->rec['GROESSENNR'];
    }

    public function getSize(): string
    {
        return $this->rec['GROESSE'];
    }

    public function getEan(): string
    {
        return $this->rec['GTIN'];
    }

    public function getVariantName(): string
    {
        return implode(' ', [$this->rec['MODELLBEZ'], $this->rec['FARBBEZ'], $this->rec['GROESSE']]);
    }

    public function getPrice(): float
    {
        return floatval(str_replace(',', '.', $this->rec['VK-PREIS']));
    }

    public function getPseudoPrice(): ?float
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getStockPerBranch(): Enumerable
    {
        return Collection::make($this->records)
            ->mapWithKeys(fn (array $r): array => [$r['GLN'] => intval($r['VERFUEGBESTAND'])]);
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

    /**
     * @inheritDoc
     */
    public function getBranches(): Enumerable
    {
        return Collection::make($this->records)
            ->pluck('GLN')
            ->unique()
            ->values();
    }

    /**
     * @inheritDoc
     */
    public function getColorVariations(): Enumerable
    {
        return $this->baseModel->getColorVariations();
    }

    public function getImportFile(): ImportFile
    {
        return $this->baseModel->getImportFile();
    }
}
