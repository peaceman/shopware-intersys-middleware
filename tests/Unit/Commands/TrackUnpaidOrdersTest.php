<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Commands;

use App\Commands\TrackUnpaidOrders;
use App\Domain\OrderTracking\OrderTracker;
use App\Domain\OrderTracking\UnpaidOrderProvider;
use Tests\TestCase;

class TrackUnpaidOrdersTest extends TestCase
{
    public function testExecution(): void
    {
        $orderTracker = $this->createMock(OrderTracker::class);
        $orderProvider = $this->createMock(UnpaidOrderProvider::class);

        $orderTracker->expects(static::once())->method('track')->with($orderProvider);

        $trackUnpaidOrders = $this->app->make(TrackUnpaidOrders::class, [
            'orderTracker' => $orderTracker,
            'orderProvider' => $orderProvider,
        ]);

        $trackUnpaidOrders();
    }
}
