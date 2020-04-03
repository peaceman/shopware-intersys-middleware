<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderArticle;
use App\Domain\Export\OrderFetched;
use App\Domain\Export\OrderSaleProvider;
use App\Domain\ShopwareAPI;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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

    public function testRequestFilters()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $orderSaleProvider = $this->createOrderSaleProvider($client);
        $orders = $orderSaleProvider->getOrders();

        static::assertEquals(0, iterator_count($orders));
        static::assertCount(2, $container);

        /** @var Request $request */
        $request = $container[0]['request'];

        $requestURI = $request->getUri();
        static::assertEquals('/api/orders', $requestURI->getPath());
        static::assertEquals('GET', $request->getMethod());
        static::assertEquals(
            [
                'filter[0][property]' => 'status',
                'filter[0][value]' => '23',
                'filter[1][property]' => 'cleared',
                'filter[1][value]' => '42',
            ],
            parse_query($requestURI->getQuery())
        );

        $request = $container[1]['request'];

        $requestURI = $request->getUri();
        static::assertEquals('/api/orders', $requestURI->getPath());
        static::assertEquals('GET', $request->getMethod());
        static::assertEquals(
            [
                'filter[0][property]' => 'status',
                'filter[0][value]' => '4',
                'filter[1][property]' => 'cleared',
                'filter[1][value]' => '8',
                'filter[2][property]' => 'paymentId',
                'filter[2][value]' => '15',
            ],
            parse_query($requestURI->getQuery())
        );
    }

    public function testOrderArticleRequests()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-orders-response.json'))),
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-55.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-59.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-61.json'))),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $orderSaleProvider = $this->createOrderSaleProvider($client);
        $orderSaleProvider->setSaleRequirements([
            [
                'status' => 23,
                'cleared' => 42,
            ],
        ]);
        $orders = $orderSaleProvider->getOrders();

        static::assertEquals(3, iterator_count($orders));
        static::assertCount(4, $container);

        $requests = array_pluck($container, 'request');
        array_shift($requests);

        $requestURIPaths = array_map(function (Request $request) { return $request->getUri()->getPath(); }, $requests);
        static::assertEquals([
            '/api/orders/55',
            '/api/orders/59',
            '/api/orders/61',
        ], $requestURIPaths);
    }

    public function testErrorResponse()
    {
        $mock = new MockHandler([
            new Response(500, []),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $orderSaleProvider = $this->createOrderSaleProvider($client);
        $orderSaleProvider->setSaleRequirements([
            [
                'status' => 23,
                'cleared' => 42,
            ],
        ]);

        /** @var Order[] $orders */
        $orders = iterator_to_array($orderSaleProvider->getOrders());
        static::assertCount(0, $orders);
    }

    public function testOrderData()
    {
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-orders-response.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-55.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-59.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-response-61.json'))),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $orderSaleProvider = $this->createOrderSaleProvider($client);
        $orderSaleProvider->setSaleRequirements([
            [
                'status' => 23,
                'cleared' => 42,
            ],
        ]);

        /** @var Order[] $orders */
        $orders = iterator_to_array($orderSaleProvider->getOrders());
        static::assertCount(3, $orders);

        foreach ($orders as $order) {
            Event::assertDispatched(OrderFetched::class, function (OrderFetched $e) use ($order) {
                return $order === $e->order;
            });
        }

        Event::assertDispatched(OrderFetched::class, 3);

        // check order 55
        $order = array_shift($orders);
        static::assertEquals('20002', $order->getOrderNumber());
        static::assertEquals('2018-10-31T20:12:42+0100', $order->getOrderTime()->format(DateTime::ISO8601));

        /** @var OrderArticle[] $articles */
        $articles = $order->getArticles();
        static::assertCount(1, $articles);

        $article = array_shift($articles);
        static::assertEquals('90389615640349', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(90, $article->getPrice());

        // check order 59
        $order = array_shift($orders);
        static::assertEquals('20003', $order->getOrderNumber());
        static::assertEquals('2018-10-31T21:36:23+0100', $order->getOrderTime()->format(DateTime::ISO8601));

        /** @var OrderArticle[] $articles */
        $articles = $order->getArticles();
        static::assertCount(3, $articles);

        $article = array_shift($articles);
        static::assertEquals('90389615640343', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(90, $article->getPrice());

        $article = array_shift($articles);
        static::assertEquals('639802-50H43001747', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(110, $article->getPrice());

        $article = array_shift($articles);
        static::assertEquals('test', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(-40, $article->getPrice());

        // check order 61
        $order = array_shift($orders);
        static::assertEquals('20004', $order->getOrderNumber());
        static::assertEquals('2018-11-01T11:55:16+0100', $order->getOrderTime()->format(DateTime::ISO8601));

        /** @var OrderArticle[] $articles */
        $articles = $order->getArticles();
        static::assertCount(2, $articles);

        $article = array_shift($articles);
        static::assertEquals('90389615640348', $article->getArticleNumber());
        static::assertEquals(3, $article->getQuantity());
        static::assertEquals(90, $article->getPrice());

        $article = array_shift($articles);
        static::assertEquals('B3747003000050', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(140, $article->getPrice());
    }

    protected function createOrderSaleProvider(Client $httpClient): OrderSaleProvider
    {
        $osp = new OrderSaleProvider(new ShopwareAPI(new NullLogger(), $httpClient), $this->app[Dispatcher::class]);
        $osp->setSaleRequirements([
            [
                'status' => 23,
                'cleared' => 42,
            ],
            [
                'status' => 4,
                'cleared' => 8,
                'paymentId' => 15,
            ],
        ]);

        return $osp;
    }
}
