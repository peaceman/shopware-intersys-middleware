<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\ShopwareAPI;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class OrderProvider
{
    private ShopwareAPI $shopwareAPI;
    private Dispatcher $eventDispatcher;
    private LoggerInterface $logger;

    public function __construct(
        ShopwareAPI $shopwareAPI,
        Dispatcher $eventDispatcher,
        LoggerInterface $logger,
    ) {
        $this->shopwareAPI = $shopwareAPI;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function getOrders(): iterable
    {
        $filters = $this->generateFilters();

        foreach ($filters as $subFilter) {
            $jsonResponse = $this->shopwareAPI->fetchOrders($subFilter);

            $apiOrders = data_get($jsonResponse, 'data', []);

            foreach ($apiOrders as $apiOrder) {
                $order = new Order($apiOrder);
                $articles = $this->fetchOrderArticles($order);

                if (Collection::make($articles)->contains(fn (OrderArticle $v): bool => !$v->isValid())) {
                    $this->logger->info(__METHOD__ . ' Ignore order that has invalid positions', [
                        'orderID' => $order->getID(),
                        'orderNumber' => $order->getOrderNumber(),
                    ]);

                    continue;
                }

                $order->setArticles($articles);

                $this->eventDispatcher->dispatch(new OrderFetched($order));

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

        return array_map(
            fn (array $v): OrderArticle  => new OrderArticle($v),
            $apiDetails,
        );
    }
}
