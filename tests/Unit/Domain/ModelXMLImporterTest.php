<?php
/**
 * lel since 11.08.18
 */

namespace Tests\Unit\Domain;

use App\Article;
use App\ArticleImport;
use App\Domain\Import\ModelXMLData;
use App\Domain\Import\ModelXMLImporter;
use App\Domain\ShopwareAPI;
use App\ImportFile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Psr\Log\NullLogger;
use Tests\TestCase;

class ModelXMLImporterTest extends TestCase
{
    use DatabaseMigrations;

    public function testDoesNotImportNonEligibleBranches()
    {
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create();
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $modelXMLImporter = new ModelXMLImporter(new NullLogger(), new ShopwareAPI(new NullLogger(), $client));
        $modelXMLImporter->setBranchesToImport(['006']);

        $xmlString = file_get_contents(base_path('docs/fixtures/model-non-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData(new ImportFile(), $xmlString));

        static::assertEmpty($container);
    }

    public function testDoesNotImportSameData()
    {
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create();
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $modelXMLImporter = new ModelXMLImporter(new NullLogger(), new ShopwareAPI(new NullLogger(), $client));
        $modelXMLImporter->setBranchesToImport(['006']);

        $article = new Article(['is_modno' => '10003436H', 'is_active' => true]);
        $article->save();

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($alreadyImportedFile, $xmlString));

        static::assertEmpty($container);
    }

    public function testDoesNotImportOldData()
    {
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create();
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $modelXMLImporter = new ModelXMLImporter(new NullLogger(), new ShopwareAPI(new NullLogger(), $client));
        $modelXMLImporter->setBranchesToImport(['006']);

        $article = new Article(['is_modno' => '10003436H', 'is_active' => true]);
        $article->save();

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();
        $newImportFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-16-23-05.xml']);
        $newImportFile->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($newImportFile, $xmlString));

        static::assertEmpty($container);
    }

    public function testUnknownArticleWillBeCreated()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(404),
            new Response(201, [], '{"success":true,"data":{"id":23,"location":"https:\/\/www.foobar.de\/api\/articles\/1008"}}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $modelXMLImporter = new ModelXMLImporter(new NullLogger(), new ShopwareAPI(new NullLogger(), $client));
        $modelXMLImporter->setBranchesToImport(['006']);

        $importFile = new ImportFile(['type' => 'base', 'original_filename' => 'lel.xml', 'storage_path' => str_random(40)]);
        $importFile->save();

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($importFile, $xmlString));

        static::assertCount(2, $container);
        /** @var \GuzzleHttp\Psr7\Request $creationRequest */
        $creationRequest = $container[1]['request'];

        $creationBody = json_decode((string)$creationRequest->getBody(), true);

        static::assertSame([
            'active' => true,
            'name' => 'MLB BASIC NY YANKEES',
            'tax' => '19.00',
            'supplier' => 'NEW ERA',
            'descriptionLong' => '',
            'mainDetail' => [
                'number' => '10003436H',
                'prices' => [[
                    'price' => 35,
                    'pseudoPrice' => null,
                ]],
            ],
            'configuratorSet' => [
                'groups' => [
                    ['name' => 'Color', 'options' => [['name' => '3436 BLACK/WHITE'], ['name' => '3438 GREY/WHITE']]],
                    ['name' => 'Size', 'options' => [['name' => 'XS'], ['name' => 'S'], ['name' => 'M'], ['name' => 'L'], ['name' => 'XL']]],
                ],
            ],
            'variants' => [
                // black white
                [
                    'additionaltext' => '3436 BLACK/WHITE',
                    'number' => '10003436HP2900004',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE XS',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3436 BLACK/WHITE'],
                        ['group' => 'Size', 'option' => 'XS'],
                    ]
                ],
                [
                    'additionaltext' => '3436 BLACK/WHITE',
                    'number' => '10003436HP2900005',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 1,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE S',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3436 BLACK/WHITE'],
                        ['group' => 'Size', 'option' => 'S'],
                    ]
                ],
                [
                    'additionaltext' => '3436 BLACK/WHITE',
                    'number' => '10003436HP2900009',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE M',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3436 BLACK/WHITE'],
                        ['group' => 'Size', 'option' => 'M'],
                    ]
                ],
                // grey white
                [
                    'additionaltext' => '3438 GREY/WHITE',
                    'number' => '10003436HP2900413',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3438 GREY/WHITE L',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3438 GREY/WHITE'],
                        ['group' => 'Size', 'option' => 'L'],
                    ]
                ],
                [
                    'additionaltext' => '3438 GREY/WHITE',
                    'number' => '10003436HP2900417',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3438 GREY/WHITE XL',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3438 GREY/WHITE'],
                        ['group' => 'Size', 'option' => 'XL'],
                    ]
                ],
            ],
        ], $creationBody);

        /** @var Article $article */
        $article = Article::query()->where('is_modno', '10003436H')->first();
        static::assertNotNull($article, 'Article was not created');
        static::assertEquals(23, $article->sw_article_id);

        /** @var ArticleImport[] $articleImports */
        $articleImports = $article->imports;
        static::assertCount(1, $articleImports);

        [$articleImport] = $articleImports;
        static::assertEquals($articleImport->import_file_id, $importFile->id);
    }

    public function testKnownArticleWillBeUpdated()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(201, [], '{"success":true,"data":{"id":23,"location":"https:\/\/www.foobar.de\/api\/articles\/1008"}}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $article = new Article(['is_modno' => '10003436H', 'is_active' => true, 'sw_article_id' => 45]);
        $article->save();

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $modelXMLImporter = new ModelXMLImporter(new NullLogger(), new ShopwareAPI(new NullLogger(), $client));
        $modelXMLImporter->setBranchesToImport(['006']);

        $importFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-21-23-05.xml', 'storage_path' => str_random(40)]);
        $importFile->save();

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($importFile, $xmlString));

        static::assertCount(1, $container);
        /** @var \GuzzleHttp\Psr7\Request $updateRequest */
        $updateRequest = $container[0]['request'];

        $updateBody = json_decode((string)$updateRequest->getBody(), true);

        static::assertSame([
            'active' => true,
            'name' => 'MLB BASIC NY YANKEES',
            'tax' => '19.00',
            'supplier' => 'NEW ERA',
            'descriptionLong' => '',
            'mainDetail' => [
                'number' => '10003436H',
                'prices' => [[
                    'price' => 35,
                    'pseudoPrice' => null,
                ]],
            ],
            'configuratorSet' => [
                'groups' => [
                    ['name' => 'Color', 'options' => [['name' => '3436 BLACK/WHITE'], ['name' => '3438 GREY/WHITE']]],
                    ['name' => 'Size', 'options' => [['name' => 'XS'], ['name' => 'S'], ['name' => 'M'], ['name' => 'L'], ['name' => 'XL']]],
                ],
            ],
            'variants' => [
                // black white
                [
                    'additionaltext' => '3436 BLACK/WHITE',
                    'number' => '10003436HP2900004',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE XS',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3436 BLACK/WHITE'],
                        ['group' => 'Size', 'option' => 'XS'],
                    ]
                ],
                [
                    'additionaltext' => '3436 BLACK/WHITE',
                    'number' => '10003436HP2900005',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 1,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE S',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3436 BLACK/WHITE'],
                        ['group' => 'Size', 'option' => 'S'],
                    ]
                ],
                [
                    'additionaltext' => '3436 BLACK/WHITE',
                    'number' => '10003436HP2900009',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE M',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3436 BLACK/WHITE'],
                        ['group' => 'Size', 'option' => 'M'],
                    ]
                ],
                // grey white
                [
                    'additionaltext' => '3438 GREY/WHITE',
                    'number' => '10003436HP2900413',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3438 GREY/WHITE L',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3438 GREY/WHITE'],
                        ['group' => 'Size', 'option' => 'L'],
                    ]
                ],
                [
                    'additionaltext' => '3438 GREY/WHITE',
                    'number' => '10003436HP2900417',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3438 GREY/WHITE XL',
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Color', 'option' => '3438 GREY/WHITE'],
                        ['group' => 'Size', 'option' => 'XL'],
                    ]
                ],
            ],
        ], $updateBody);

        /** @var Article $article */
        $article = Article::query()->where('is_modno', '10003436H')->first();

        /** @var ArticleImport[] $articleImports */
        $articleImports = $article->imports;
        static::assertCount(2, $articleImports);

        [, $articleImport] = $articleImports;
        static::assertEquals($articleImport->import_file_id, $importFile->id);
    }
}