<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class ModelCSV implements ModelDTO
{
    private ImportFile $importFile;
    private array $records;
    private array $rec;

    public function __construct(
        ImportFile $importFile,
        array $records,
    ) {
        if (empty($records))
            throw new \InvalidArgumentException("ModelCSV records can not be empty");

        $this->importFile = $importFile;
        $this->records = $records;

        [$this->rec] = $this->records;
    }

    public function getModelName(): string
    {
        return $this->rec['MODELLBEZ'];
    }

    public function getModelNumber(): string
    {
        return $this->rec['MODELLNR'];
    }

    public function getVatPercentage(): float
    {
        // todo configurable vat percentages
        return match ($this->rec['STEUER']) {
            (string) 3 => 19.0,
            (string) 2 => 7.0,
            default => 0,
        };
    }

    public function getManufacturerName(): string
    {
        return $this->rec['SUBMARKE'];
    }

    public function getTargetGroupGender(): ?TargetGroupGender
    {
        return TargetGroupGender::tryFromWarengruppe($this->rec['WARENGRUPPE']);
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
        return Collection::make($this->records)
            ->groupBy(fn (array $record): string => ModelColorCSV::sanitizeColorNumber($record['FARBNR']))
            ->map(fn (Collection $colorRecords): ModelColorCSV => new ModelColorCSV($this, $colorRecords->all()))
            ->values();
    }

    public function getImportFile(): ImportFile
    {
        return $this->importFile;
    }
}
