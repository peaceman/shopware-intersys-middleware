<?php
/**
 * lel since 08.11.18
 */
namespace App\Domain\Export;

class OrderFetched
{
    /**
     * @var Order
     */
    public $order;

    /**
     * OrderFetched constructor.
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }
}
