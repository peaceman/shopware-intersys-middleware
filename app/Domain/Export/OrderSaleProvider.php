<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class OrderSaleProvider extends OrderProvider
{
    /**
     * @var array
     */
    protected $saleRequirements;

    /**
     * @param array $saleRequirements
     */
    public function setSaleRequirements(array $saleRequirements): void
    {
        $this->saleRequirements = $saleRequirements;
    }

    public function generateFilters(): array
    {
        return [
            ['property' => 'status', 'value' => $this->saleRequirements['status']],
            ['property' => 'cleared', 'value' => $this->saleRequirements['cleared']],
        ];
    }
}
