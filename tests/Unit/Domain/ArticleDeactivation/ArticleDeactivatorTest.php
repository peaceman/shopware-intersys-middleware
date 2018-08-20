<?php
/**
 * lel since 20.08.18
 */

namespace Tests\Unit\Domain\ArticleDeactivation;


use App\Article;
use App\Domain\Import\ArticleDeactivation\ArticleIDProvider;
use App\Domain\ShopwareAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Psr\Log\NullLogger;
use Tests\TestCase;

class ArticleDeactivatorTest extends TestCase
{
    use DatabaseMigrations;

    public function testArticleDeactivation()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200),
            new Response(200),
            new Response(200),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $articles = factory(Article::class, 3)->create();

        $shopwareAPI = new ShopwareAPI(new NullLogger(), $client);
        $deactivator = new \App\Domain\Import\ArticleDeactivation\ArticleDeactivator(new NullLogger(), $shopwareAPI);
        $deactivator->deactivate(new class($articles) implements ArticleIDProvider {
            protected $articles;

            public function __construct($articles)
            {
                $this->articles = $articles;
            }

            public function getArticleIDs(): iterable
            {
                return collect($this->articles)->pluck('id');
            }
        });

        static::assertCount(3, $container);
        foreach ($articles as $idx => $article) {
            $article->refresh();

            /** @var Request $deactivationRequest */
            $deactivationRequest = $container[$idx]['request'];
            static::assertEquals('PUT', $deactivationRequest->getMethod());
            static::assertEquals(['active' => false], json_decode($deactivationRequest->getBody(), true));
            static::assertEquals("/api/articles/{$article->sw_article_id}", $deactivationRequest->getUri());

            static::assertFalse($article->is_active);
        }
    }
}