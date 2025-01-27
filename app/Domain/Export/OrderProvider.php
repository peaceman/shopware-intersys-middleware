<?php

namespace App\Domain\Export;

interface OrderProvider
{
    /**
     * @return iterable<OrderDTO>
     */
    public function getOrders(): iterable;
}
