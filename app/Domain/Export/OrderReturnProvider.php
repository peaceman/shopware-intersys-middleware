<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class OrderReturnProvider extends OrderProvider
{
    /**
     * @var array
     */
    protected $returnRequirements;

    public function generateFilters(): array
    {
        return [
            ['property' => 'status', 'value' => $this->returnRequirements['status']],
            ['property' => 'cleared', 'value' => $this->returnRequirements['cleared']],
        ];
    }

    /**
     * @param array $returnRequirements
     */
    public function setReturnRequirements(array $returnRequirements): void
    {
        $this->returnRequirements = $returnRequirements;
    }
}
