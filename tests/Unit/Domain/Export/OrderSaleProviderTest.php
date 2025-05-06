<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderFetched;
use App\Domain\Export\ShopwareOrderSaleProvider;
use App\Domain\Shopware6API;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Psr\Log\NullLogger;
use Tests\TestCase;
use function GuzzleHttp\Psr7\parse_query;

class OrderSaleProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function testOrderFetching(): void
    {
        // mocks
        $shopwareApi = $this->createMock(Shopware6API::class);

        $shopwareApi->expects(static::once())
            ->method('listOrders')
            ->with([
                'filter' => [
                    ['type' => 'equals', 'field' => 'deliveries.stateMachineState.technicalName', 'value' => 'shipped'],
                    ['type' => 'equals', 'field' => 'intersys.exportedAt', 'value' => null],
                ],
                'includes' => [
                    'order' => ['id', 'orderNumber', 'lineItems', 'orderDateTime'],
                    'order_line_items' => ['type', 'quantity', 'price', 'product'],
                    'calculated_price' => ['unitPrice', 'totalPrice'],
                    'product' => ['ean', 'productNumber'],
                ],
                'associations' => [
                    'intersys' => [],
                    'lineItems' => [
                        'associations' => [
                            'product' => [],
                        ],
                    ],
                ],
            ])
            ->willReturn([new Order([])]);

        // execution
        $orderSaleProvider = new ShopwareOrderSaleProvider(
            $shopwareApi,
            $this->app[Dispatcher::class],
            new NullLogger(),
        );

        $orders = [...$orderSaleProvider->getOrders()];

        // assertions
        static::assertNotEmpty($orders);

        foreach ($orders as $order) {
            Event::assertDispatched(OrderFetched::class, function (OrderFetched $e) use ($order) {
                return $order === $e->order;
            });
        }

        Event::assertDispatched(OrderFetched::class, count($orders));
    }
}
