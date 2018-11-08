<?php
/**
 * lel since 08.11.18
 */

namespace App\Domain\OrderTracking;


use App\Domain\Export\OrderFetched;
use App\Domain\ShopwareAPI;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

class OrderTracker
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ShopwareAPI
     */
    private $shopwareAPI;

    /**
     * @var Dispatcher
     */
    private $eventDispatcher;

    public function __construct(LoggerInterface $logger, Dispatcher $eventDispatcher, ShopwareAPI $shopwareAPI)
    {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function track(OrderProvider $orderProvider)
    {
        $startTime = microtime(true);
        $this->logger->info(__METHOD__ . ' Start');
        /** @var \App\Order $order */
        foreach ($orderProvider->getOrders() as $order) {
            $this->trackOrder($order);
        }

        $elapsed = microtime(true) - $startTime;
        $this->logger->info(__METHOD__ . ' End', ['elapsed' => $elapsed]);
    }

    public function trackOrder(\App\Order $order): void
    {
        $this->logger->info(__METHOD__, $order->attributesToArray());
        $orderDetails = $this->shopwareAPI->fetchOrderDetails($order->sw_order_id);
        if (!$orderDetails) return;

        $exportOrder = new \App\Domain\Export\Order($orderDetails['data']);
        $this->eventDispatcher->dispatch(new OrderFetched($exportOrder));
    }
}
