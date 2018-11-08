<?php
/**
 * lel since 08.11.18
 */

namespace Tests\Unit\Domain\OrderTracking;


use App\Domain\Export\OrderFetched;
use App\Domain\OrderTracking\OrderProvider;
use App\Domain\OrderTracking\OrderTracker;
use App\Domain\ShopwareAPI;
use App\Order;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Psr\Log\NullLogger;
use Tests\TestCase;

class OrderTrackerTest extends TestCase
{
    use DatabaseMigrations;

    public function testOrderTracking()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-55.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-59.json'))),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $orders = factory(Order::class, 2)->create();
        $orderProvider = $this->createOrderProviderFromOrders($orders);

        Event::fake();
        $orderTracker = new OrderTracker(
            new NullLogger(),
            $this->app[Dispatcher::class],
            new ShopwareAPI(new NullLogger(), $client)
        );
        $orderTracker->track($orderProvider);

        static::assertCount(2, $container);
        /** @var Request $request */
        $request = $container[0]['request'];
        static::assertEquals("/api/orders/{$orders[0]->sw_order_id}", $request->getUri()->getPath());
        $request = $container[1]['request'];
        static::assertEquals("/api/orders/{$orders[1]->sw_order_id}", $request->getUri()->getPath());

        $events = Event::dispatched(OrderFetched::class, function (OrderFetched $e) {
            return collect([55, 59])->contains($e->order->getID());
        });

        static::assertCount(2, $events);
    }

    private function createOrderProviderFromOrders(iterable $orders): OrderProvider
    {
        return new class($orders) implements OrderProvider
        {
            private $orders;

            public function __construct(iterable $orders)
            {
                $this->orders = $orders;
            }

            public function getOrders(): iterable
            {
                return $this->orders;
            }
        };
    }
}
