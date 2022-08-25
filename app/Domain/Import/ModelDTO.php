<?php

namespace App\Domain\Import;

use App\ImportFile;
use Illuminate\Support\Enumerable;

interface ModelDTO
{
    public function getModelName(): string;
    public function getModelNumber(): string;
    public function getVatPercentage(): float;
    public function getManufacturerName(): string;
    public function getTargetGroupGender(): ?TargetGroupGender;

    /**
     * @return Enumerable<array-key, string>
     */
    public function getBranches(): Enumerable;

    /**
     * @return Enumerable<array-key, ModelColorDTO>
     */
    public function getColorVariations(): Enumerable;

    public function getImportFile(): ImportFile;
}
