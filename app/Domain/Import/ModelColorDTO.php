<?php

namespace App\Domain\Import;

use Illuminate\Support\Enumerable;

interface ModelColorDTO extends ModelDTO
{
    public function getMainArticleNumber(): string;
    public function getColorNumber(): string;
    public function getColorName(): string;

    /**
     * @return Enumerable<array-key, ModelColorDTO>
     */
    public function getSizeVariations(): Enumerable;
}
