<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function compareDateTime(\DateTimeInterface $dateTimeA, \DateTimeInterface $dateTimeB)
    {
        return $dateTimeA->format(\DateTime::ISO8601) === $dateTimeB->format(\DateTime::ISO8601);
    }
}
