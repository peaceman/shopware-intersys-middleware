<?php
/**
 * lel since 13.03.20
 */

namespace App\Commands;

use App\Domain\OrderTracking\OrderTracker;
use App\Domain\OrderTracking\UnpaidOrderProvider;

class TrackUnpaidOrders
{
    /** @var OrderTracker */
    private $orderTracker;

    /** @var UnpaidOrderProvider */
    private $orderProvider;

    public function __construct(OrderTracker $orderTracker, UnpaidOrderProvider $orderProvider)
    {
        $this->orderTracker = $orderTracker;
        $this->orderProvider = $orderProvider;
    }

    public function __invoke(): void
    {
        $this->orderTracker->track($this->orderProvider);
    }
}
