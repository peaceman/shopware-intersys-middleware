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
        return array_map(function (array $reqs): array {
            return array_map(function ($reqVal, $reqKey): array {
                return ['property' => $reqKey, 'value' => $reqVal];
            }, $reqs, array_keys($reqs));
        }, $this->saleRequirements);
    }
}
