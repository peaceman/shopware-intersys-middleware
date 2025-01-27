<?php

namespace App\Domain\Export;

class FakeOrderProvider implements OrderProvider
{
    protected $orders;

    public function __construct(iterable $orders)
    {
        $this->orders = $orders;
    }

    /**
     * @inheritDoc
     */
    public function getOrders(): iterable
    {
        return $this->orders;
    }
}
