<?php
/**
 * lel since 08.11.18
 */

namespace App\Domain\OrderTracking;


interface OrderProvider
{
    public function getOrders(): iterable;
}
