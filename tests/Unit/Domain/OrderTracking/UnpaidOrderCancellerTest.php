<?php
/**
 * lel since 08.11.18
 */

namespace Tests\Unit\Domain\OrderTracking;


use App\Domain\OrderTracking\UnpaidOrderCanceller;
use App\Domain\ShopwareAPI;
use App\Order;
use App\OrderArticle;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Psr\Log\NullLogger;
use Tests\TestCase;

class UnpaidOrderCancellerTest extends TestCase
{
    use DatabaseMigrations;

    private $returnOrderStatusRequirement = 4;
    private $returnOrderPositionStatusRequirement = 8;

    public function testOrderCancellation()
    {
        /** @var Order $order */
        $order = factory(Order::class)->create();
        $orderArticles = factory(OrderArticle::class, 2)->make();
        $order->orderArticles()->saveMany($orderArticles);

        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $orderProvider = $this->createOrderProviderFromOrders([$order]);
        $orderCanceller = new UnpaidOrderCanceller(new NullLogger(), new ShopwareAPI(new NullLogger(), $client));
        $orderCanceller->setReturnOrderStatusRequirement($this->returnOrderStatusRequirement)
            ->setReturnOrderPositionStatusRequirement($this->returnOrderPositionStatusRequirement);

        $orderCanceller->cancel($orderProvider);

        static::assertCount(1, $container);

        /** @var Request $request */
        $request = $container[0]['request'];
        static::assertEquals("/api/orders/{$order->sw_order_id}", $request->getUri()->getPath());
        static::assertEquals('PUT', $request->getMethod());

        $requestBody = json_decode((string)$request->getBody(), true);
        static::assertEquals(
            [
                'orderStatusId' => $this->returnOrderStatusRequirement,
                'details' => [
                    ['id' => $orderArticles[0]->sw_position_id, 'status' => $this->returnOrderPositionStatusRequirement],
                    ['id' => $orderArticles[1]->sw_position_id, 'status' => $this->returnOrderPositionStatusRequirement],
                ],
            ],
            $requestBody
        );

        static::assertNotNull($order->refresh()->cancelled_at);
    }
}
