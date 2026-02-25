<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\Shopware6API;
use Psr\Log\LoggerInterface;

class ShopwareOrderReturnProvider implements OrderProvider
{
    protected Shopware6API $shopwareApi;
    protected LoggerInterface $logger;

    public function __construct(
        Shopware6API $shopwareApi,
        LoggerInterface $logger,
    ) {
        $this->shopwareApi = $shopwareApi;
        $this->logger = $logger;
    }

    public function getOrders(): iterable
    {
        $response = $this->shopwareApi->listTransferableDvsnReturnShipmentsRaw();

        return collect($response['data'])
            ->map($this->mapOrder(...))
            ->filter()
            ->toArray();
    }

    protected function mapOrder(array $orderData): ?OrderDTO
    {
        $lineItems = collect($orderData['lineItems'])
            ->map(fn (array $lineItemData): ?array => $this->mapOrderLineItem($orderData, $lineItemData))
            ->filter()
            ->toArray();

        return new Order([
            'id' => $orderData['id'],
            'orderDateTime' => $orderData['createdAt'],
            'orderNumber' => $orderData['order']['orderNumber'],
            'lineItems' => $lineItems,
        ]);
    }

    protected function mapOrderLineItem(array $orderData, array $lineItemData): ?array
    {
        $restockQuantity = $lineItemData['quantity'];
        if ($restockQuantity === 0) return null;

        $orderLineItem = $lineItemData['orderLineItem'];
        $unitPrice = $orderLineItem['price']['unitPrice'];

        return [
            'product' => [
                'ean' => $orderLineItem['product']['ean'] ?? '',
                'productNumber' => $orderLineItem['product']['productNumber'],
            ],
            'quantity' => $restockQuantity,
            'price' => [
                'unitPrice' => $unitPrice,
                'totalPrice' => $restockQuantity * $unitPrice,
            ],
            'type' => $orderLineItem['type'],
        ];
    }
}
