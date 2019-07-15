<?php
/**
 * lel since 2019-07-15
 */

namespace App\Domain\HouseKeeping;

use App\ImportFile;

class OldImportFileProvider
{
    /** @var int */
    protected $keepDurationInDays;

    public function setKeepDurationInDays(int $keepDurationInDays): void
    {
        $this->keepDurationInDays = $keepDurationInDays;
    }

    public function provide(): iterable
    {
        $query = ImportFile::query()
            ->whereDate('created_at', '<=', now()->subDays($this->keepDurationInDays));

        return $query->cursor();
    }
}
