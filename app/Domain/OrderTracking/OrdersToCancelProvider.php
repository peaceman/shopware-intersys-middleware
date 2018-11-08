<?php
/**
 * lel since 08.11.18
 */

namespace App\Domain\OrderTracking;


use App\Order;

class OrdersToCancelProvider implements OrderProvider
{
    /**
     * @var int[]
     */
    private $unpaidPaymentStatusIDs;

    /**
     * @var int
     */
    private $prePaymentID;

    /**
     * @var int
     */
    private $cancelWaitingTimeInDays;

    /**
     * @return iterable
     */
    public function getOrders(): iterable
    {
        $query = Order::query()
            ->whereIn('sw_payment_status_id', $this->unpaidPaymentStatusIDs)
            ->where('sw_payment_id', $this->prePaymentID)
            ->whereNull('cancelled_at')
            ->whereDate('sw_order_time', '<=', now()->subDays($this->cancelWaitingTimeInDays));

        return $query->get();
    }

    /**
     * @param int[] $unpaidPaymentStatusIDs
     * @return OrdersToCancelProvider
     */
    public function setUnpaidPaymentStatusIDs(array $unpaidPaymentStatusIDs): OrdersToCancelProvider
    {
        $this->unpaidPaymentStatusIDs = $unpaidPaymentStatusIDs;
        return $this;
    }

    /**
     * @param int $prePaymentID
     * @return OrdersToCancelProvider
     */
    public function setPrePaymentID(int $prePaymentID): OrdersToCancelProvider
    {
        $this->prePaymentID = $prePaymentID;
        return $this;
    }

    /**
     * @param int $cancelWaitingTimeInDays
     * @return OrdersToCancelProvider
     */
    public function setCancelWaitingTimeInDays(int $cancelWaitingTimeInDays): OrdersToCancelProvider
    {
        $this->cancelWaitingTimeInDays = $cancelWaitingTimeInDays;
        return $this;
    }
}
