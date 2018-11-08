<?php
/**
 * lel since 08.11.18
 */

namespace App\Domain\OrderTracking;


use App\Order;

class DailyOverviewOrderProvider implements OrderProvider
{
    public function getOrders(): iterable
    {
        $query = Order::query()
            ->whereNull('notified_at')
            ->with('orderArticles');

        return $query->get();
    }
}
