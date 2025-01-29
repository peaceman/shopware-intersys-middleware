<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderArticle;
use App\Domain\Export\OrderFetched;
use App\Domain\Export\ShopwareOrderSaleProvider;
use App\Domain\Shopware6API;
use App\Domain\ShopwareAPI;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Arr;
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
                ],
                'includes' => [
                    'order' => ['id', 'orderNumber', 'lineItems', 'orderDateTime'],
                    'order_line_items' => ['type', 'quantity', 'price', 'product'],
                    'calculated_price' => ['unitPrice', 'totalPrice'],
                    'product' => ['ean', 'productNumber'],
                ],
                'associations' => [
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
