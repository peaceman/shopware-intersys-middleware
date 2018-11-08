<?php

namespace App\Listeners;

use App\Domain\Export\Order as ExportOrder;
use App\Domain\Export\OrderArticle as ExportOrderArticle;
use App\Domain\Export\OrderFetched;
use App\Order;
use App\OrderArticle;

class PersistOrder
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderFetched  $event
     * @return void
     */
    public function handle(OrderFetched $event): void
    {
        $exportOrder = $event->order;

        if ($order = $this->fetchOrderForExportOrder($exportOrder)) {
            $this->updateOrder($order, $exportOrder);
        } else {
            $this->createOrder($exportOrder);
        }
    }

    private function fetchOrderForExportOrder(ExportOrder $exportOrder): ?Order
    {
        return Order::query()
            ->where('sw_order_number', $exportOrder->getOrderNumber())
            ->first();
    }

    private function createOrder(ExportOrder $exportOrder): void
    {
        $order = new Order();
        $order->sw_order_id = $exportOrder->getID();
        $order->sw_order_number = $exportOrder->getOrderNumber();
        $order->sw_order_time = $exportOrder->getOrderTime();
        $order->sw_order_status_id = $exportOrder->getOrderStatusID();
        $order->sw_payment_status_id = $exportOrder->getPaymentStatusID();
        $order->sw_payment_id = $exportOrder->getPaymentID();
        $order->save();

        foreach ($exportOrder->getArticles() as $exportOrderArticle) {
            $this->createOrderArticles($order, $exportOrderArticle);
        }
    }

    private function createOrderArticles(Order $order, ExportOrderArticle $exportOrderArticle): void
    {
        $orderArticle = new OrderArticle();
        $orderArticle->sw_position_id = $exportOrderArticle->getPositionID();
        $orderArticle->sw_article_number = $exportOrderArticle->getArticleNumber();
        $orderArticle->sw_article_name = $exportOrderArticle->getArticleName();
        $orderArticle->sw_quantity = $exportOrderArticle->getQuantity();

        $order->orderArticles()->save($orderArticle);
    }

    private function updateOrder(Order $order, ExportOrder $exportOrder): void
    {
        $order->sw_order_status_id = $exportOrder->getOrderStatusID();
        $order->sw_payment_status_id = $exportOrder->getPaymentStatusID();
        $order->save();
    }
}
