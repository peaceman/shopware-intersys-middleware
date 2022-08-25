<?php

namespace App\Domain\Import;

use Illuminate\Support\Enumerable;

interface ModelColorSizeDTO extends ModelColorDTO
{
    public function getVariantArticleNumber(): string;
    public function getSize(): string;
    public function getEan(): string;
    public function getVariantName(): string;
    public function getPrice(): float;
    public function getPseudoPrice(): ?float;

    /**
     * @return Enumerable<string, int>
     */
    public function getStockPerBranch(): Enumerable;
}
