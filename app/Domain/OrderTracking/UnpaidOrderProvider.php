<?php
/**
 * lel since 08.11.18
 */

namespace App\Domain\OrderTracking;

use App\Order;

class UnpaidOrderProvider implements OrderProvider
{
    /**
     * @var int[]
     */
    private $unpaidPaymentStatusIDs;

    public function getOrders(): iterable
    {
        $query = Order::query()
            ->whereNull('cancelled_at')
            ->whereIn('sw_payment_status_id', $this->unpaidPaymentStatusIDs);

        return $query->get();
    }

    /**
     * @param int[] $unpaidPaymentStatusIDs
     * @return UnpaidOrderProvider
     */
    public function setUnpaidPaymentStatusIDs(array $unpaidPaymentStatusIDs): UnpaidOrderProvider
    {
        $this->unpaidPaymentStatusIDs = $unpaidPaymentStatusIDs;
        return $this;
    }
}
