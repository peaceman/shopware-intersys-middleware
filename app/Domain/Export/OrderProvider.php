<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\ShopwareAPI;

class OrderProvider
{
    /**
     * @var ShopwareAPI
     */
    private $shopwareAPI;

    public function __construct(ShopwareAPI $shopwareAPI)
    {
        $this->shopwareAPI = $shopwareAPI;
    }

    public function getOrders(): iterable
    {
        $filters = $this->generateFilters();

        foreach ($filters as $subFilter) {
            $jsonResponse = $this->shopwareAPI->fetchOrders($subFilter);

            $apiOrders = data_get($jsonResponse, 'data', []);

            foreach ($apiOrders as $apiOrder) {
                $order = new Order($apiOrder);
                $order->setArticles($this->fetchOrderArticles($order));

                yield $order;
            }
        }
    }

    public function generateFilters(): array
    {
        return [];
    }

    /**
     * @param Order $order
     * @return array|OrderArticle[]
     */
    protected function fetchOrderArticles(Order $order): array
    {
        $jsonResponse = $this->shopwareAPI->fetchOrderDetails($order->getID());

        $apiDetails = data_get($jsonResponse, 'data.details', []);

        return array_map(function (array $apiOrderArticle): OrderArticle {
            return new OrderArticle($apiOrderArticle);
        }, $apiDetails);
    }
}
