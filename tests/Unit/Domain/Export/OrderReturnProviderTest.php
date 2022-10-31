<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderArticle;
use App\Domain\Export\OrderReturnProvider;
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
use Illuminate\Support\Facades\Event;
use Psr\Log\NullLogger;
use Tests\TestCase;
use function GuzzleHttp\Psr7\parse_query;

class OrderReturnProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function testFilters(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $orp = $this->createOrderReturnProvider($client);

        $orders = $orp->getOrders();

        static::assertEquals(0, iterator_count($orders));
        static::assertCount(1, $container);

        /** @var Request $request */
        $request = $container[0]['request'];

        $requestURI = $request->getUri();
        static::assertEquals('/api/orders', $requestURI->getPath());
        static::assertEquals('GET', $request->getMethod());
        static::assertEquals(
            [
                'filter[0][property]' => 'status',
                'filter[0][value]' => '23',
                'sort[0][property]' => 'orderTime',
                'sort[0][direction]' => 'DESC',
                'filter[1][property]' => 'cleared',
                'filter[1][value]' => '42',
            ],
            Query::parse($requestURI->getQuery())
        );
    }

    /**
     * @param $client
     * @return OrderReturnProvider
     */
    private function createOrderReturnProvider($client): OrderReturnProvider
    {
        $orp = new OrderReturnProvider(
            new ShopwareAPI(new NullLogger(), $client),
            $this->app[Dispatcher::class],
            new NullLogger(),
        );

        $orp->setRequirements([[
            'status' => 23,
            'cleared' => 42,
        ]]);
        return $orp;
    }

    public function testOrderData()
    {
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-orders-return-response.json'))),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/shopware-api-order-details-return-response-55.json'))),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $orderReturnProvider = $this->createOrderReturnProvider($client);

        /** @var Order[] $orders */
        $orders = iterator_to_array($orderReturnProvider->getOrders());
        static::assertCount(1, $orders);

        // check order 55
        $order = array_shift($orders);
        static::assertEquals('20002', $order->getOrderNumber());
        static::assertEquals('2018-10-31T20:12:42+0100', $order->getOrderTime()->format(DateTime::ISO8601));

        /** @var OrderArticle[] $articles */
        $articles = $order->getArticles();
        static::assertCount(2, $articles);

        $article = array_shift($articles);
        static::assertEquals('90389615640349', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(90, $article->getPrice());

        $article = array_shift($articles);
        static::assertEquals('90389615640350', $article->getArticleNumber());
        static::assertEquals(1, $article->getQuantity());
        static::assertEquals(90, $article->getPrice());
    }
}
