<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\Shopware6API;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

class ShopwareOrderSaleProvider implements OrderProvider
{
    protected Shopware6API $shopwareApi;
    protected Dispatcher $dispatcher;
    protected LoggerInterface $logger;

    public function __construct(
        Shopware6API $shopware6API,
        Dispatcher $dispatcher,
        LoggerInterface $logger,
    ) {
        $this->shopwareApi = $shopware6API;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    public function getOrders(): iterable
    {
        $filters = [
            ['type' => 'equals', 'field' => 'stateMachineState.technicalName', 'value' => 'open'],
            ['type' => 'multi', 'operator' => 'or', 'queries' => [
                ['type' => 'multi', 'operator' => 'and', 'queries' => [
                    ['type' => 'not', 'queries' => [
                        ['type' => 'equals', 'field' => 'transactions.paymentMethod.technicalName', 'value' => 'payment_prepayment'],
                    ]],
                    ['type' => 'equals', 'field' => 'transactions.stateMachineState.technicalName', 'value' => 'paid'],
                ]],
                ['type' => 'equals', 'field' => 'transactions.paymentMethod.technicalName', 'value' => 'payment_prepayment'],
            ]],
        ];

        $includes = [
            'order' => ['id', 'orderNumber', 'lineItems', 'orderDateTime'],
            'order_line_items' => ['type', 'quantity', 'price', 'product'],
            'calculated_price' => ['unitPrice', 'totalPrice'],
            'product' => ['ean', 'productNumber'],
        ];

        $associations = [
            'lineItems' => [
                'associations' => [
                    'product' => [],
                ],
            ],
        ];

        $criteria = [
            'filter' => $filters,
            'includes' => $includes,
            'associations' => $associations,
        ];

        $orders = $this->shopwareApi->listOrders($criteria);

        foreach ($orders as $order) {
            $this->dispatcher->dispatch(new OrderFetched($order));

            yield $order;
        }
    }
}
