<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\OrderReturnProvider;
use App\Domain\ShopwareAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use Tests\TestCase;
use function GuzzleHttp\Psr7\parse_query;

class OrderReturnProviderTest extends TestCase
{
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

        $orp = new OrderReturnProvider(new ShopwareAPI(new NullLogger(), $client));
        $orp->setReturnRequirements([
            'status' => 23,
            'cleared' => 42,
        ]);

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
                'filter[1][property]' => 'cleared',
                'filter[1][value]' => '42',
            ],
            parse_query($requestURI->getQuery())
        );
    }
}
