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
            ->method('listCompletedReturnOrdersRaw')
            ->willReturn(json_decode(fixture_content('shopware/pickware-erp-return-orders-response.json'), true));

        // call the implementation
        $orderProvider = new ShopwareOrderReturnProvider($shopwareApi, new NullLogger());
        $orders = [...$orderProvider->getOrders()];

        // assertions
        static::assertContainsOnlyInstancesOf(OrderDTO::class, $orders);

        // qty 2 -> 1 disposed, 1 restocked
        /** @var OrderDTO $order */
        $order = collect($orders)->firstOrFail(fn (OrderDTO $v): bool => $v->getId() === '0193bacd65e677e3ad1b2df73a2b3877');
        static::assertCount(1, $order->getLineItems());

        [$lineItem] = $order->getLineItems();
        static::assertEquals(1, $lineItem->getQuantity());

        // qty 3 -> 1 disposed, 2 restocked
        /** @var OrderDTO $order */
        $order = collect($orders)->firstOrFail(fn (OrderDTO $v): bool => $v->getId() === '0194b22ab98f7726b1a4dcb0c03fba28');
        static::assertCount(1, $order->getLineItems());

        [$lineItem] = $order->getLineItems();
        static::assertEquals(2, $lineItem->getQuantity());

        // qty 4 -> 4 disposed, 0 restocked
        /** @var OrderDTO $order */
        $order = collect($orders)->firstOrFail(fn (OrderDTO $v): bool => $v->getId() === '0194b21c8c157316b7d0cec5bde82ea1');
        static::assertCount(0, $order->getLineItems());
    }
}
