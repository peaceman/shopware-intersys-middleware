<?php

namespace Tests;

use App\Domain\OrderTracking\OrderProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function compareDateTime(\DateTimeInterface $dateTimeA, \DateTimeInterface $dateTimeB)
    {
        return $dateTimeA->format(\DateTime::ISO8601) === $dateTimeB->format(\DateTime::ISO8601);
    }

    protected function createOrderProviderFromOrders(iterable $orders): OrderProvider
    {
        return new class($orders) implements OrderProvider
        {
            private $orders;

            public function __construct(iterable $orders)
            {
                $this->orders = $orders;
            }

            public function getOrders(): iterable
            {
                return $this->orders;
            }
        };
    }
}
