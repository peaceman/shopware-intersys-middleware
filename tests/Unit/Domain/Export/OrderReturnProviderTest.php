<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\OrderDTO;
use App\Domain\Export\ShopwareOrderReturnProvider;
use App\Domain\Shopware6API;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use function GuzzleHttp\Psr7\parse_query;

class OrderReturnProviderTest extends TestCase
{

    public function testOrderData()
    {
        // setup mocks
        $shopwareApi = $this->createMock(Shopware6API::class);
        $shopwareApi->expects(static::once())
            ->method('listTransferableDvsnReturnShipmentsRaw')
            ->willReturn(json_decode(fixture_content('shopware/dvsn-return-shipments-response.json'), true));

        // call the implementation
        $orderProvider = new ShopwareOrderReturnProvider($shopwareApi, new NullLogger());
        $orders = [...$orderProvider->getOrders()];

        // assertions
        static::assertContainsOnlyInstancesOf(OrderDTO::class, $orders);

        /** @var OrderDTO $order */
        $order = collect($orders)->firstOrFail(fn (OrderDTO $v): bool => $v->getId() === '019c707c816a72e8a9266e8dab9fbd73');
        static::assertEquals(
            ['orderNumber' => '1039622'],
            ['orderNumber' => $order->getOrderNumber()],
        );
        static::assertCount(1, $order->getLineItems());

        [$lineItem] = $order->getLineItems();
        static::assertEquals(
            [
                'ean' => '4067902508726',
                'productNumber' => 'JC5806030000612',
                'quantity' => 3,
                'price' => 3 * 23.77,
                'unitPrice' => 23.77,
            ],
            [
                'ean' => $lineItem->getEan(),
                'productNumber' => $lineItem->getProductNumber(),
                'quantity' => $lineItem->getQuantity(),
                'price' => $lineItem->getPrice(),
                'unitPrice' => $lineItem->getUnitPrice(),
            ],
        );

        // ensure that the order provider does not crash on non product line items
        $order = collect($orders)->firstOrFail(fn (OrderDTO $v): bool => $v->getId() === 'not a product order id');
        static::assertCount(1, $order->getLineItems());

        [$lineItem] = $order->getLineItems();
        static::assertFalse($lineItem->isProduct());
    }
}
