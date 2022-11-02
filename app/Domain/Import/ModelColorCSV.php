<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ModelColorCSV implements ModelColorDTO
{
    private ModelCSV $baseModel;
    private array $records;
    private array $rec;

    public function __construct(
        ModelCSV $baseModel,
        array $records,
    ) {
        if (empty($records))
            throw new \InvalidArgumentException("ModelColorCSV records can not be empty");

        $this->baseModel = $baseModel;
        $this->records = $records;
        [$this->rec] = $records;
    }

    public function getMainArticleNumber(): string
    {
        return $this->getModelNumber() . $this->getColorNumber();
    }

    public function getColorNumber(): string
    {
        return static::sanitizeColorNumber($this->rec['FARBNR']);
    }

    public function getColorName(): string
    {
        return $this->rec['FARBBEZ'];
    }

    /**
     * @inheritDoc
     */
    public function getSizeVariations(): Enumerable
    {
        return Collection::make($this->records)
            ->groupBy('GROESSENNR')
            ->map(fn (Collection $sizeRecords): ModelColorSizeCSV => new ModelColorSizeCSV($this, $sizeRecords->all()))
            ->values();
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

    public static function sanitizeColorNumber(string $colorNumber): string
    {
        return preg_replace('/\s+/', '', $colorNumber);
    }
}
