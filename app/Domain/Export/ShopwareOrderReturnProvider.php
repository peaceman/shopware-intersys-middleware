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
        $response = $this->shopwareApi->listCompletedReturnOrdersRaw();

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
        $restockQuantity = $lineItemData['quantity'] - $this->determineDisposedQuantity($orderData, $lineItemData);
        if ($restockQuantity === 0) return null;

        return [
            'product' => [
                'ean' => $lineItemData['product']['ean'] ?? '',
                'productNumber' => $lineItemData['productNumber'],
            ],
            'quantity' => $restockQuantity,
            'price' => [
                'unitPrice' => $lineItemData['unitPrice'],
                'totalPrice' => $restockQuantity * $lineItemData['unitPrice'],
            ],
            'type' => $lineItemData['type'],
        ];
    }

    protected function determineDisposedQuantity(array $orderData, array $lineItemData): int
    {
        $productId = $lineItemData['productId'];

        return collect($orderData['sourceStockMovements'])
            ->where('productId', '=', $productId)
            ->where('destinationLocationTypeTechnicalName', '=', 'special_stock_location')
            ->sum('quantity');
    }
}
