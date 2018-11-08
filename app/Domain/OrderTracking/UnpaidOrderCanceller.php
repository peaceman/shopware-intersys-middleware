<?php
/**
 * lel since 08.11.18
 */

namespace App\Domain\OrderTracking;


use App\Domain\ShopwareAPI;
use App\OrderArticle;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class UnpaidOrderCanceller
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
     * @var int
     */
    private $returnOrderStatusRequirement;

    /**
     * @var int
     */
    private $returnOrderPositionStatusRequirement;

    /**
     * UnpaidOrderCanceller constructor.
     * @param LoggerInterface $logger
     * @param ShopwareAPI $shopwareAPI
     */
    public function __construct(LoggerInterface $logger, ShopwareAPI $shopwareAPI)
    {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
    }

    /**
     * @param int $returnOrderStatusRequirement
     * @return UnpaidOrderCanceller
     */
    public function setReturnOrderStatusRequirement(int $returnOrderStatusRequirement): UnpaidOrderCanceller
    {
        $this->returnOrderStatusRequirement = $returnOrderStatusRequirement;
        return $this;
    }

    /**
     * @param int $returnOrderPositionStatusRequirement
     * @return UnpaidOrderCanceller
     */
    public function setReturnOrderPositionStatusRequirement(int $returnOrderPositionStatusRequirement): UnpaidOrderCanceller
    {
        $this->returnOrderPositionStatusRequirement = $returnOrderPositionStatusRequirement;
        return $this;
    }

    public function cancel(OrderProvider $orderProvider)
    {
        $startTime = microtime(true);
        $this->logger->info(__METHOD__ . ' Start');
        /** @var \App\Order $order */
        foreach ($orderProvider->getOrders() as $order) {
            $this->cancelOrder($order);
        }

        $elapsed = microtime(true) - $startTime;
        $this->logger->info(__METHOD__ . ' End', ['elapsed' => $elapsed]);
    }

    public function cancelOrder(\App\Order $order)
    {
        $this->logger->info(__METHOD__, $order->attributesToArray());

        $details = collect($order->orderArticles)
            ->map(function (OrderArticle $orderArticle) {
                return [
                    'id' => $orderArticle->sw_position_id,
                    'status' => $this->returnOrderPositionStatusRequirement,
                ];
            })
            ->all();

        rescue(function () use ($order, $details) {
            DB::transaction(function () use ($order, $details) {
                $order->cancel();

                $this->shopwareAPI->updateOrderStatus(
                    $order->sw_order_id,
                    $this->returnOrderStatusRequirement,
                    $details
                );
            });
        });
    }
}
