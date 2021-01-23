<?php

namespace App\Domain\Import;

class SizeMappingRequest
{
    public function __construct(
        private string $manufacturerName,
        private string $mainArticleNumber,
        private string $variantArticleNumber,
        private string $size,
        private ?string $fedas,
    ) {}

    public function getManufacturerName(): string
    {
        return $this->manufacturerName;
    }

    public function getMainArticleNumber(): string
    {
        return $this->mainArticleNumber;
    }

    public function getVariantArticleNumber(): string
    {
        return $this->variantArticleNumber;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getFedas(): ?string
    {
        return $this->fedas;
    }
}
