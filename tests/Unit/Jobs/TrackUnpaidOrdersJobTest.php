<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Jobs;

use App\Commands\TrackUnpaidOrders;
use App\Jobs\TrackUnpaidOrdersJob;
use Tests\TestCase;

class TrackUnpaidOrdersJobTest extends TestCase
{
    public function testExecution(): void
    {
        $trackUnpaidOrders = $this->createMock(TrackUnpaidOrders::class);
        $trackUnpaidOrders->expects(static::once())->method('__invoke');

        $trackUnpaidOrdersJob = $this->app->make(TrackUnpaidOrdersJob::class);
        $trackUnpaidOrdersJob->handle($trackUnpaidOrders);
    }
}
