<?php
/**
 * lel since 11.08.18
 */

namespace Tests\Unit\Domain;

use App\Article;
use App\ArticleImport;
use App\Domain\Import\ModelXMLData;
use App\Domain\Import\ModelXMLImporter;
use App\Domain\Import\SizeMapper;
use App\Domain\ShopwareAPI;
use App\ImportFile;
use App\Manufacturer;
use App\ManufacturerSizeMapping;
use GuzzleHttp\Client;
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

        $modelXMLImporter = $this->createModelXMLImporterWithHTTPClient($client);
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

        $modelXMLImporter = $this->createModelXMLImporterWithHTTPClient($client);
        $modelXMLImporter->setBranchesToImport(['006']);

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();

        $article = new Article(['is_modno' => '10003436H000', 'is_active' => true]);
        $article->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $article = new Article(['is_modno' => '10003436H004', 'is_active' => true]);
        $article->save();

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

        $modelXMLImporter = $this->createModelXMLImporterWithHTTPClient($client);
        $modelXMLImporter->setBranchesToImport(['006']);

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();
        $newImportFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-16-23-05.xml']);
        $newImportFile->save();

        $article = new Article(['is_modno' => '10003436H000', 'is_active' => true]);
        $article->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $article = new Article(['is_modno' => '10003436H004', 'is_active' => true]);
        $article->save();

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
            new Response(404),
            new Response(201, [], '{"success":true,"data":{"id":24,"location":"https:\/\/www.foobar.de\/api\/articles\/1009"}}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $this->createSizeMappings();

        $modelXMLImporter = $this->createModelXMLImporterWithHTTPClient($client);
        $modelXMLImporter->setBranchesToImport(['006']);

        $importFile = new ImportFile(['type' => 'base', 'original_filename' => 'lel.xml', 'storage_path' => str_random(40)]);
        $importFile->save();

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($importFile, $xmlString));

        static::assertCount(4, $container);

        // check first article
        /** @var \GuzzleHttp\Psr7\Request $creationRequest */
        $creationRequest = $container[1]['request'];

        $creationBody = json_decode((string)$creationRequest->getBody(), true);

        static::assertSame([
            'active' => false,
            'name' => 'MLB BASIC NY YANKEES (3436 BLACK/WHITE)',
            'tax' => '19.00',
            'supplier' => 'NEW ERA',
            'descriptionLong' => '',
            'lastStock' => true,
            'mainDetail' => [
                'number' => '10003436H000',
                'prices' => [[
                    'price' => 35,
                    'pseudoPrice' => null,
                ]],
                'weight' => Article::DEFAULTS_WEIGHT,
                'shippingTime' => Article::DEFAULTS_SHIPPING_TIME,
            ],
            'configuratorSet' => [
                'type' => 2,
                'groups' => [
                    ['name' => 'Size', 'options' => [['name' => 'R'], ['name' => 'S'], ['name' => 'M']]],
                ],
            ],
            'variants' => [
                [
                    'active' => true,
                    'number' => '10003436HP2900004',
                    'ean' => '',
                    'lastStock' => true,
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE XS',
                        'availability' => json_encode([
                            [
                                'branchNo' => '009',
                                'stock' => 8,
                            ],
                            [
                                'branchNo' => '011',
                                'stock' => 23,
                            ]
                        ]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'R'],
                    ]
                ],
                [
                    'active' => true,
                    'number' => '10003436HP2900005',
                    'ean' => '',
                    'lastStock' => true,
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 1,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE S',
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'S'],
                    ]
                ],
                [
                    'active' => true,
                    'number' => '10003436HP2900009',
                    'ean' => '',
                    'lastStock' => true,
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3436 BLACK/WHITE M',
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'M'],
                    ]
                ],
            ],
        ], $creationBody);

        /** @var Article $article */
        $article = Article::query()->where('is_modno', '10003436H000')->first();
        static::assertNotNull($article, 'Article was not created');
        static::assertEquals(23, $article->sw_article_id);

        /** @var ArticleImport[] $articleImports */
        $articleImports = $article->imports;
        static::assertCount(1, $articleImports);

        [$articleImport] = $articleImports;
        static::assertEquals($articleImport->import_file_id, $importFile->id);

        // check second article
        /** @var \GuzzleHttp\Psr7\Request $creationRequest */
        $creationRequest = $container[3]['request'];

        $creationBody = json_decode((string)$creationRequest->getBody(), true);

        static::assertSame([
            'active' => false,
            'name' => 'MLB BASIC NY YANKEES (3438 GREY/WHITE)',
            'tax' => '19.00',
            'supplier' => 'NEW ERA',
            'descriptionLong' => '',
            'lastStock' => true,
            'mainDetail' => [
                'number' => '10003436H004',
                'prices' => [[
                    'price' => 35,
                    'pseudoPrice' => null,
                ]],
                'weight' => Article::DEFAULTS_WEIGHT,
                'shippingTime' => Article::DEFAULTS_SHIPPING_TIME,
            ],
            'configuratorSet' => [
                'type' => 2,
                'groups' => [
                    ['name' => 'Size', 'options' => [['name' => 'L'], ['name' => 'XL']]],
                ],
            ],
            'variants' => [
                [
                    'active' => true,
                    'number' => '10003436HP2900413',
                    'ean' => '',
                    'lastStock' => true,
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3438 GREY/WHITE L',
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'L'],
                    ]
                ],
                [
                    'active' => true,
                    'number' => '10003436HP2900417',
                    'ean' => '',
                    'lastStock' => true,
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'attr1' => 'MLB BASIC NY YANKEES 3438 GREY/WHITE XL',
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'XL'],
                    ]
                ],
            ],
        ], $creationBody);

        /** @var Article $article */
        $article = Article::query()->where('is_modno', '10003436H004')->first();
        static::assertNotNull($article, 'Article was not created');
        static::assertEquals(24, $article->sw_article_id);

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
            new Response(200, [], file_get_contents(base_path('docs/fixtures/price-unprotected-article-response.json'))),
            new Response(201, [], '{"success":true,"data":{"id":23,"location":"https:\/\/www.foobar.de\/api\/articles\/1008"}}'),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/price-unprotected-article-response.json'))),
            new Response(201, [], '{"success":true,"data":{"id":24,"location":"https:\/\/www.foobar.de\/api\/articles\/1008"}}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $this->createSizeMappings();

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();

        $article = new Article(['is_modno' => '10003436H000', 'is_active' => true, 'sw_article_id' => 23]);
        $article->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $article = new Article(['is_modno' => '10003436H004', 'is_active' => true, 'sw_article_id' => 24]);
        $article->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $modelXMLImporter = $this->createModelXMLImporterWithHTTPClient($client);
        $modelXMLImporter->setBranchesToImport(['006']);

        $importFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-21-23-05.xml', 'storage_path' => str_random(40)]);
        $importFile->save();

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($importFile, $xmlString));

        static::assertCount(4, $container);

        // check first article
        /** @var \GuzzleHttp\Psr7\Request $updateRequest */
        $updateRequest = $container[1]['request'];

        $updateBody = json_decode((string)$updateRequest->getBody(), true);

        static::assertSame([
            'mainDetail' => [
                'prices' => [[
                    'price' => 35,
                    'pseudoPrice' => null,
                ]],
            ],
            'configuratorSet' => [
                'type' => 2,
                'groups' => [
                    ['name' => 'Size', 'options' => [['name' => 'R'], ['name' => 'S'], ['name' => 'M']]],
                ],
            ],
            'variants' => [
                [
                    'number' => '10003436HP2900004',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'availability' => json_encode([
                            [
                                'branchNo' => '009',
                                'stock' => 8,
                            ],
                            [
                                'branchNo' => '011',
                                'stock' => 23,
                            ]
                        ]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'R'],
                    ]
                ],
                [
                    'number' => '10003436HP2900005',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 1,
                    'attribute' => [
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'S'],
                    ]
                ],
                [
                    'number' => '10003436HP2900009',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 2,
                    'attribute' => [
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'M'],
                    ]
                ],
            ],
        ], $updateBody);

        /** @var Article $article */
        $article = Article::query()->where('is_modno', '10003436H000')->first();

        /** @var ArticleImport[] $articleImports */
        $articleImports = $article->imports;
        static::assertCount(2, $articleImports);

        [, $articleImport] = $articleImports;
        static::assertEquals($articleImport->import_file_id, $importFile->id);

        // check second article
        /** @var \GuzzleHttp\Psr7\Request $updateRequest */
        $updateRequest = $container[3]['request'];

        $updateBody = json_decode((string)$updateRequest->getBody(), true);

        static::assertSame([
            'mainDetail' => [
                'prices' => [[
                    'price' => 35,
                    'pseudoPrice' => null,
                ]],
            ],
            'configuratorSet' => [
                'type' => 2,
                'groups' => [
                    ['name' => 'Size', 'options' => [['name' => 'L'], ['name' => 'XL']]],
                ],
            ],
            'variants' => [
                [
                    'number' => '10003436HP2900413',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'L'],
                    ]
                ],
                [
                    'number' => '10003436HP2900417',
                    'ean' => '',
                    'prices' => [[
                        'price' => 35,
                        'pseudoPrice' => null,
                    ]],
                    'inStock' => 0,
                    'attribute' => [
                        'availability' => json_encode([]),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => 'XL'],
                    ]
                ],
            ],
        ], $updateBody);

        /** @var Article $article */
        $article = Article::query()->where('is_modno', '10003436H004')->first();

        /** @var ArticleImport[] $articleImports */
        $articleImports = $article->imports;
        static::assertCount(2, $articleImports);

        [, $articleImport] = $articleImports;
        static::assertEquals($articleImport->import_file_id, $importFile->id);
    }

    public function testArticlePriceWontBeUpdatedIfItIsWriteProtected()
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(base_path('docs/fixtures/full-price-protected-article-response.json'))),
            new Response(201, [], '{"success":true,"data":{"id":23,"location":"https:\/\/www.foobar.de\/api\/articles\/1008"}}'),
            new Response(200, [], file_get_contents(base_path('docs/fixtures/partial-price-protected-article-response.json'))),
            new Response(201, [], '{"success":true,"data":{"id":24,"location":"https:\/\/www.foobar.de\/api\/articles\/1008"}}'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client([
            'handler' => $stack,
        ]);

        $alreadyImportedFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-19-23-05.xml']);
        $alreadyImportedFile->save();

        $articleA = new Article(['is_modno' => '10003436H000', 'is_active' => true, 'sw_article_id' => 23]);
        $articleA->save();

        $articleA->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $articleB = new Article(['is_modno' => '10003436H004', 'is_active' => true, 'sw_article_id' => 24]);
        $articleB->save();

        $articleB->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $modelXMLImporter = $this->createModelXMLImporterWithHTTPClient($client);
        $modelXMLImporter->setBranchesToImport(['006']);

        $importFile = new ImportFile(['type' => 'base', 'original_filename' => '2018-08-21-23-05.xml', 'storage_path' => str_random(40)]);
        $importFile->save();

        $xmlString = file_get_contents(base_path('docs/fixtures/model-eligible.xml'));
        $modelXMLImporter->import(new ModelXMLData($importFile, $xmlString));

        // the complete article is price write protected
        /** @var Request $articleAInfoRequest */
        $articleAInfoRequest = $container[0]['request'];
        static::assertNotNull($articleAInfoRequest);
        static::assertEquals('GET', $articleAInfoRequest->getMethod());
        static::assertEquals("/api/articles/{$articleA->sw_article_id}", $articleAInfoRequest->getUri()->getPath());

        /** @var Request $articleAUpdateRequest */
        $articleAUpdateRequest = $container[1]['request'];
        static::assertNotNull($articleAUpdateRequest);
        static::assertEquals('PUT', $articleAUpdateRequest->getMethod());
        static::assertEquals(
            "/api/articles/{$articleA->sw_article_id}",
            $articleAUpdateRequest->getUri()->getPath()
        );

        $updateBody = json_decode((string)$articleAUpdateRequest->getBody(), true);
        static::assertNull(data_get($updateBody, 'mainDetail.prices'));
        static::assertContainsOnly('null', data_get($updateBody, 'variants.*.prices'));

        // just a variant of the article is write protected
        /** @var Request $articleBInfoRequest */
        $articleBInfoRequest = $container[2]['request'];
        static::assertNotNull($articleBInfoRequest);
        static::assertEquals('GET', $articleBInfoRequest->getMethod());
        static::assertEquals("/api/articles/{$articleB->sw_article_id}", $articleBInfoRequest->getUri()->getPath());

        $articleBUpdateRequest = $container[3]['request'];
        static::assertNotNull($articleBUpdateRequest);
        static::assertEquals('PUT', $articleBUpdateRequest->getMethod());
        static::assertEquals(
            "/api/articles/{$articleB->sw_article_id}",
            $articleBUpdateRequest->getUri()->getPath()
        );

        $updateBody = json_decode((string)$articleBUpdateRequest->getBody(), true);
        static::assertNotNull(data_get($updateBody, 'mainDetail.prices'));
        static::assertThat(
            data_get($updateBody, 'variants.*.prices'),
            static::logicalNot(static::containsOnly('null'))
        );
    }

    /**
     * @param $client
     * @return ModelXMLImporter
     */
    protected function createModelXMLImporterWithHTTPClient($client): ModelXMLImporter
    {
        return new ModelXMLImporter(new NullLogger(), new ShopwareAPI(new NullLogger(), $client), new SizeMapper());
    }

    protected function createSizeMappings(): void
    {
        /** @var Manufacturer $manufacturer */
        $manufacturer = Manufacturer::unguarded(function () {
            $m = new Manufacturer(['name' => 'NEW ERA']);
            $m->save();

            return $m;
        });

        ManufacturerSizeMapping::unguarded(function () use ($manufacturer) {
            $manufacturer->sizeMappings()->create([
                'gender' => ManufacturerSizeMapping::GENDER_FEMALE,
                'source_size' => 'XS',
                'target_size' => 'R',
            ]);
        });
    }
}
