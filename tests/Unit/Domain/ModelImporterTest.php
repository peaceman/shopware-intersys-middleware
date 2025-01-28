<?php

namespace Tests\Unit\Domain;

use App\Article;
use App\ArticleImport;
use App\Domain\Import\ImportFileReader;
use App\Domain\Import\Manufacturer\ManufacturerDTO;
use App\Domain\Import\Manufacturer\ManufacturerImporter;
use App\Domain\Import\Manufacturer\ManufacturerImporterFake;
use App\Domain\Import\ModelColorDTO;
use App\Domain\Import\ModelColorSizeDTO;
use App\Domain\Import\ModelColorSizeTest;
use App\Domain\Import\ModelCSV;
use App\Domain\Import\ModelDTO;
use App\Domain\Import\ModelImporter;
use App\Domain\Import\ModelTest;
use App\Domain\Import\ProductDTO;
use App\Domain\Import\PropertyGroup\PropertyGroupDTORaw;
use App\Domain\Import\PropertyGroup\PropertyGroupImporter;
use App\Domain\Import\PropertyGroup\PropertyGroupImporterFake;
use App\Domain\Import\PropertyGroup\PropertyGroupOptionDTO;
use App\Domain\Import\SizeMapper;
use App\Domain\Import\SizeMappingRequest;
use App\Domain\Shopware6API;
use App\ImportFile;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Arr;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Utils\ArgRecorder;

// TODO this is not a unit test and should be moved to the feature tests
class ModelImporterTest extends TestCase
{
    use DatabaseMigrations;

    protected ?Filesystem $localFS;
    protected ?PropertyGroupImporter $propertyGroupImporter;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->localFS = Storage::disk('local');
        $this->propertyGroupImporter = new PropertyGroupImporterFake();
    }

    public function testGlnFilter(): void
    {
        $api = $this->createShopwareApiThatExpectsToNotBeUsed();

        $importer = $this->createModelImporterWithApi($api);
        $importer->setGlnToImport('gln');

        $models = new LazyCollection(fn () => $this->readModelsFromFixture('Base4WebShop-100343-20220822114000.csv'));
        $importer->import($models->first());
    }

    public function testDoesNotImportOldData(): void
    {
        $api = $this->createShopwareApiThatExpectsToNotBeUsed();

        $importer = $this->createModelImporterWithApi($api);
        $importer->setGlnToImport('4399901690509');

        $alreadyImportedFile = new ImportFile([
            'type' => ImportFile::TYPE_BASE,
            'original_filename' => 'Base4WebShop-100343-20230822114000.csv',
        ]);
        $alreadyImportedFile->save();

        $article = new Article([
            'is_modno' => '00003566194104',
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ]);
        $article->save();

        $article->imports()->create(['import_file_id' => $alreadyImportedFile->id]);

        $models = new LazyCollection(fn () => $this->readModelsFromFixture('Base4WebShop-100343-20220822114000.csv'));
        $importer->import($models->first());
    }

    public function testNewProductWillBeCreated(): void
    {
        $shopwareApi = $this->createMock(Shopware6API::class);
        $manufacturerImporter = $this->createMock(ManufacturerImporter::class);
        $propertyGroupImporter = $this->createMock(PropertyGroupImporter::class);

        $modelImporter = $this->createModelImporterWithApi(
            $shopwareApi,
            manufacturerImporter: $manufacturerImporter,
            propertyGroupImporter: $propertyGroupImporter,
        );

        $modelImporter->setGlnToImport($glnToImport = '4399901690509');

        $importFile = $this->createImportFileFromFixture($this->localFS, 'Base4WebShop-100343-20220822114000.csv');
        $models = new LazyCollection(fn () => (new ImportFileReader(new NullLogger(), $this->localFS))($importFile));
        /** @var ModelDTO $model */
        $model = $models->first(fn (ModelDTO $modelDTO): bool => $modelDTO->getBranches()->contains($glnToImport));

        // mocks

        $shopwareApi->expects(static::atLeastOnce())
            ->method('findProductByProductNumber')
            ->with($model->getColorVariations()->first()->getMainArticleNumber())
            ->willReturn(null);

        $manufacturerImporter->expects(static::once())
            ->method('import')
            ->with($model->getManufacturerName())
            ->willReturn($manufacturer = new ManufacturerDTO([
                'id' => Str::random(32),
                'name' => $model->getManufacturerName(),
            ]));

        $sizes = $model->getColorVariations()
            ->flatMap(function (ModelColorDTO $color): Enumerable {
                return $color->getSizeVariations()
                    ->map(fn (ModelColorSizeDTO $size): string => $size->getSize());
            })
            ->toArray();

        $propertyGroupImporter->expects(static::once())
            ->method('import')
            ->with(
                ModelImporter::PROPERTY_GROUP_NAME_SIZE,
                $sizes
            )
            ->willReturn($propertyGroupDto = new PropertyGroupDTORaw([
                'id' => Str::random(32),
                'name' => ModelImporter::PROPERTY_GROUP_NAME_SIZE,
                'options' => collect($sizes)
                    ->map(fn(string $size): array => ['id' => Str::random(32), 'name' => $size])
                    ->toArray(),
            ]));

        $shopwareApi->expects(static::any())
            ->method('createProduct')
            ->with(static::callback($productDataArgRecorder = new ArgRecorder()))
            ->willReturn(new ProductDTO(['id' => $newProductId = Str::random(32)]));

        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchTaxIdByVatPercentage')
            ->with(19.0)
            ->willReturn($taxId = Str::random(32));

        // execution
        $modelImporter->import($model);

        // assertions
        [$productData] = $productDataArgRecorder->args;
        static::assertNotNull($productData);

        static::assertFalse($productData['active']); // new products should not be active by default
        static::assertNotNull($productData['name'] ?? null, 'missing product name');
        static::assertNotNull($productData['stock'] ?? null, 'missing product stock'); // shopware 6 requires stock for the main product
        static::assertEquals($taxId, $productData['taxId'] ?? null);
        static::assertTrue($productData['isCloseout'], 'missing is closeout');

        [$price] = $productData['price'];
        static::assertNotNull($price);

        static::assertEquals($currencyId, $price['currencyId'] ?? null);

        // list price and regular price are the same during the initial product creation
        static::assertEquals($price['gross'], $price['listPrice']['gross']);
        static::assertEquals($price['net'], $price['listPrice']['net']);
        static::assertNull($price['listPrice']['currencyId'] ?? null, 'currency id was supplied in list price');

        foreach ($productData['children']->all() as $productVariant) {
            [$price] = $productVariant['price'];
            static::assertNotNull($price);

            static::assertEquals($price['gross'], $price['listPrice']['gross']);
            static::assertEquals($price['net'], $price['listPrice']['net']);
            static::assertNull($price['listPrice']['currencyId'] ?? null, 'currency id was supplied in list price');
            static::assertNull($productVariant['active'], 'variants must not have an active state to keep inheritance of the field');

            static::assertCount(1, $productVariant['options'], 'variant should contain a single option that defines the size');
            [$option] = $productVariant['options'];
            static::assertEquals($propertyGroupDto->getId(), $option['groupId']);
            static::assertContains($option['id'], $propertyGroupDto->getOptions()->map(fn ($v) => $v->getId()));
        }

        // assert that configuratorSettings is filled with all variant defining property group options
        $expectedConfiguratorSettings = $propertyGroupDto->getOptions()
            ->map(fn(PropertyGroupOptionDTO $option): array => ['optionId' => $option->getId()]);

        static::assertEquals($expectedConfiguratorSettings->toArray(), $productData['configuratorSettings']);

        // assert that the new product will also be locally stored
        $modelColor = $model->getColorVariations()->first();
        /** @var Article $article */
        $article = Article::query()->where('is_modno', $modelColor->getMainArticleNumber())->first();
        static::assertNotNull($article);
        static::assertTrue($article->is_active);
        static::assertEquals($article->sw_product_id, $newProductId);

        // assert that the used import file is linked
        static::assertEquals(1, $article->imports()->count());
        /** @var ?ArticleImport $articleImport **/
        $articleImport = $article->imports()->latest()->first();
        static::assertEquals($articleImport->import_file_id, $importFile->id);
    }

    public function testProductThatExistsInShopwareButNotLocallyWillBeUpdated(): void
    {
        $shopwareApi = $this->createMock(Shopware6API::class);

        $modelImporter = $this->createModelImporterWithApi($shopwareApi);
        $modelImporter->setGlnToImport($glnToImport = '4399901690509');

        $models = new LazyCollection(fn () => $this->readModelsFromFixture('Base4WebShop-100343-20220822114000.csv'));
        /** @var ModelDTO $model */
        $model = $models->first(fn (ModelDTO $modelDTO): bool => $modelDTO->getBranches()->contains($glnToImport));
        $modelColor = $model->getColorVariations()->first();

        // mocks
        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        $shopwareApi->expects(static::once())
            ->method('findProductByProductNumber')
            ->with($modelColor->getMainArticleNumber())
            ->willReturn($productDto = new ProductDTO(['id' => $productId = Str::random(32)]));

        $shopwareApi->expects(static::once())
            ->method('getProductById')
            ->with($productId)
            ->willReturn($productDto);

        $shopwareApi->expects(static::once())
            ->method('updateProduct')
            ->with($productId, static::anything());

        // execution
        $modelImporter->import($model);

        // assertions
        /** @var ?Article $article */
        $article = Article::query()->where('is_modno', $modelColor->getMainArticleNumber())->first();
        static::assertNotNull($article, 'could not find article');
        static::assertEquals($productId, $article->sw_product_id);
    }

    public function testExistingProductWillBeUpdated(): void
    {
        $shopwareApi = $this->createMock(Shopware6API::class);
        $propertyGroupImporter = $this->createMock(PropertyGroupImporter::class);

        $modelImporter = $this->createModelImporterWithApi(
            $shopwareApi,
            propertyGroupImporter: $propertyGroupImporter,
        );

        $modelImporter->setGlnToImport($glnToImport = '4399901690509');

        $models = new LazyCollection(fn () => $this->readModelsFromFixture('Base4WebShop-100343-20220822114000.csv'));
        /** @var ModelDTO $model */
        $model = $models->first(fn (ModelDTO $modelDTO): bool => $modelDTO->getBranches()->contains($glnToImport));
        $modelColor = $model->getColorVariations()->first();

        $article = new Article([
            'is_modno' => $modelColor->getMainArticleNumber(),
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ]);

        $article->save();

        $sizes = $model->getColorVariations()
            ->flatMap(function (ModelColorDTO $color): Enumerable {
                return $color->getSizeVariations()
                    ->map(fn (ModelColorSizeDTO $size): string => $size->getSize());
            })
            ->toArray();

        $propertyGroup = new PropertyGroupDTORaw([
            'id' => Str::random(32),
            'name' => ModelImporter::PROPERTY_GROUP_NAME_SIZE,
            'options' => collect(['omega', 'XL'])
                ->merge($sizes)
                ->map(fn(string $size): array => ['id' => Str::random(32), 'name' => $size])
                ->toArray(),
        ]);

        $newImportFile = tap(new ImportFile(['original_filename' => 'kekw.csv', 'type' => ImportFile::TYPE_BASE]), fn($v) => $v->save());
        $firstSizeVariation = $modelColor->getSizeVariations()->first();
        $importModel = new ModelTest($newImportFile, [
            'name' => '',
            'number' => $modelColor->getModelNumber(),
            'gln' => $glnToImport,
            'colorVariations' => [
                [
                    'colorName' => $modelColor->getColorName(),
                    'colorNumber' => $modelColor->getColorNumber(),
                    'sizeVariations' => [
                        [
                            'articleNumber' => $firstSizeVariation->getVariantArticleNumber(),
                            'size' => 'omega',
                            'ean' => $firstSizeVariation->getEan(),
                            'price' => 23.5,
                            'stock' => 38,
                        ],
                        [
                            'articleNumber' => 'test article number' . Str::random(),
                            'size' => 'XL',
                            'ean' => Str::random(8),
                            'price' => 23.5,
                            'stock' => 90,
                        ]
                    ],
                ],
            ],
        ]);

        $sizeVariationModelAlpha = $importModel->getColorVariations()->first()
            ->getSizeVariations()->get(0);

        $sizeVariationModelBeta = $importModel->getColorVariations()->first()
            ->getSizeVariations()->get(1);

        // mocks
        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        // article exists in the local database so there should be no lookup necessary
        $shopwareApi->expects(static::never())
            ->method('findProductByProductNumber')
            ->with($article->is_modno);

        $productDto = new ProductDTO([
            'id' => $article->sw_product_id,
            'price' => [
                'gross' => 23,
                'net' => 21,
                'listPrice' => null,
            ],
            'children' => [
                ...$modelColor->getSizeVariations()
                    ->map(function (ModelColorSizeDTO $modelColorSizeDto) {
                        return [
                            'id' => Str::random(32),
                            'ean' => $modelColorSizeDto->getEan(),
                            'stock' => 23,
                            'customFields' => [
                                'sim_protected_price' => true,
                            ],
                            'price' => [
                                [
                                    'net' => 21,
                                    'gross' => 23,
                                    'listPrice' => null,
                                ]
                            ],
                        ];
                    }),
            ],
        ]);

        $shopwareApi->expects(static::once())
            ->method('getProductById')
            ->with($article->sw_product_id)
            ->willReturn($productDto);

        $propertyGroupImporter->expects(static::once())
            ->method('import')
            ->with(ModelImporter::PROPERTY_GROUP_NAME_SIZE, ['omega', 'XL'])
            ->willReturn($propertyGroup);

        $shopwareApi->expects(static::once())
            ->method('updateProduct')
            ->with($article->sw_product_id, static::callback($updateProductArgRecorder = new ArgRecorder()))
            ->willReturn(new ProductDTO([]));

        $shopwareApi->expects(static::once())
            ->method('createProduct')
            ->with(static::callback($createProductArgRecorder = new ArgRecorder()))
            ->willReturn(new ProductDTO([]));

        // execution

        $modelImporter->import($importModel);

        // assertions
        $updateProductData = $updateProductArgRecorder->latest();
        static::assertIsArray($updateProductData);
        static::assertTrue($updateProductData['isCloseout'], 'missing is closeout');
        static::assertCount(1, $updateProductData['children']);

        [$updateProductChildData] = $updateProductData['children'];

        // check that the missing list price was added
        static::assertEquals(
            [
                'net' => $sizeVariationModelAlpha->getPrice() / (1 + ($sizeVariationModelAlpha->getVatPercentage() / 100)),
                'gross' => $sizeVariationModelAlpha->getPrice(),
            ],
            Arr::only(current($updateProductChildData['price'])['listPrice'], ['net', 'gross']),
        );

        // check that the stock was updated in the correct variant
        $swVariantId = collect($productDto->getChildren())
            ->firstWhere('ean', $sizeVariationModelAlpha->getEan())['id'];

        $updateStockProductChildData = collect($updateProductData['children'] ?? [])
            ->firstWhere('id', $swVariantId);

        static::assertNotNull($updateStockProductChildData, 'failed to find variant that should be updated');
        static::assertEquals($sizeVariationModelAlpha->getStockPerBranch()->first(), $updateStockProductChildData['stock']);

        // check that a new size variant will be added
        $updateNewVariantProductChildData = collect($updateProductData['children'] ?? [])
            ->firstWhere('ean', $sizeVariationModelBeta->getEan());

        static::assertNull($updateNewVariantProductChildData, 'new variant cant be in the parent products update data');

        $createProductData = $createProductArgRecorder->latest();
        static::assertEquals([
            'parentId' => $article->sw_product_id,
            'active' => null,
            'stock' => $sizeVariationModelBeta->getStockPerBranch()[$glnToImport],
            'ean' => $sizeVariationModelBeta->getEan(),
            'price' => [
                [
                    'gross' => $sizeVariationModelBeta->getPrice(),
                    'net' => $sizeVariationModelBeta->getPrice() / (1 + ($sizeVariationModelBeta->getVatPercentage() / 100)),
                    'currencyId' => $currencyId,
                    'linked' => false,
                    'listPrice' => [
                        'gross' => $sizeVariationModelBeta->getPrice(),
                        'net' => $sizeVariationModelBeta->getPrice() / (1 + ($sizeVariationModelBeta->getVatPercentage() / 100)),
                        'linked' => false,
                    ],
                ],
            ],
            'productNumber' => $sizeVariationModelBeta->getVariantArticleNumber(),
            'options' => [
                [
                    'groupId' => $propertyGroup->getId(),
                    'id' => $propertyGroup->getOptionByName($sizeVariationModelBeta->getSize())->getId(),
                ],
            ],
        ], $createProductData);
    }

    public function testVariantAdditionWithNewConfiguratorSetting()
    {
        $oldImportFile = tap(
            new ImportFile(['original_filename' => '1.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $oldModel = new ModelTest($oldImportFile, [
            'name' => 'name of kek/alpha-23',
            'number' => 'kek/alpha-23',
            'gln' => $glnToImport = Str::random(13),
            'colorVariations' => [
                [
                    'colorName' => 'black',
                    'colorNumber' => '0x00',
                    'sizeVariations' => [
                        [
                            'size' => 'L',
                        ]
                    ],
                ],
            ],
        ]);

        /** @var ModelColorDTO $oldColorVariation */
        $oldColorVariation = $oldModel->getColorVariations()->first();

        $article = tap(new Article([
            'is_modno' => $oldColorVariation->getMainArticleNumber(),
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ]), fn($v) => $v->save());

        $newImportFile = tap(
            new ImportFile(['original_filename' => '2.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $newModel = new ModelTest($newImportFile, [
            'name' => $oldModel->getModelName(),
            'number' => $oldModel->getModelNumber(),
            'gln' => $oldModel->getBranches()->first(),
            'colorVariations' => [
                [
                    'colorName' => $oldColorVariation->getColorName(),
                    'colorNumber' => $oldColorVariation->getColorNumber(),
                    'sizeVariations' => [
                        [
                            'size' => 'XL',
                        ]
                    ],
                ],
            ],
        ]);

        $productDto = new ProductDTO([
            'id' => $article->sw_product_id,
            'children' => $oldColorVariation->getSizeVariations()
                ->map(function (ModelColorSizeDTO $colorSizeDto) use ($glnToImport): array {
                    return [
                        'id' => (string) Str::uuid()->getHex(),
                        'ean' => $colorSizeDto->getEan(),
                        'stock' => $colorSizeDto->getStockPerBranch()->get($glnToImport),
                    ];
                })
                ->toArray(),
            'configuratorSettings' => $oldColorVariation
                ->getSizeVariations()
                ->map(function (ModelColorSizeDTO $colorSizeDto): array {
                    $optionId = (string) Str::uuid()->getHex();

                    return [
                        'id' => (string) Str::uuid()->getHex(),
                        'optionId' => $optionId,
                        'option' => [
                            'id' => $optionId,
                            'name' => $colorSizeDto->getSize(),
                        ]
                    ];
                })
                ->toArray(),
        ]);

        // mocks
        $shopwareApi = $this->createMock(Shopware6API::class);
        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        $shopwareApi->expects(static::once())
            ->method('getProductById')
            ->with($article->sw_product_id)
            ->willReturn($productDto);

        $shopwareApi->expects(static::once())
            ->method('updateProduct')
            ->with($article->sw_product_id, static::callback($updateProductArgRecorder = new ArgRecorder()));

        $propertyGroupImporter = new PropertyGroupImporterFake();

        // execution
        $modelImporter = $this->createModelImporterWithApi($shopwareApi, propertyGroupImporter: $propertyGroupImporter);
        $modelImporter->setGlnToImport($glnToImport);

        $modelImporter->import($newModel);

        // assertions
        $updateProductData = $updateProductArgRecorder->latest();
        static::assertIsArray($updateProductData);
        static::assertContainsOnly('null', Arr::pluck($updateProductData['children'], 'id'));

        // check that configuratorSettings contains only the new option
        static::assertIsArray($updateProductData['configuratorSettings']);
        static::assertCount(1, $updateProductData['configuratorSettings']);

        $configuratorSetting = $updateProductData['configuratorSettings'][0];
        static::assertEquals(
            [
                'optionId' => $propertyGroupImporter->import(ModelImporter::PROPERTY_GROUP_NAME_SIZE, [])
                    ->getOptionByName('XL')->getId()
            ],
            $configuratorSetting,
        );
    }

    public function testVariantUpdatePriceProtection()
    {
        $oldImportFile = tap(
            new ImportFile(['original_filename' => '1.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $oldModel = new ModelTest($oldImportFile, [
            'name' => 'name of kek/alpha-23',
            'number' => 'kek/alpha-23',
            'gln' => $glnToImport = Str::random(13),
            'colorVariations' => [
                [
                    'colorName' => 'black',
                    'colorNumber' => '0x00',
                    'sizeVariations' => [
                        ['size' => 'L', 'price' => 100],
                        ['size' => 'XL', 'price' => 200],
                        ['size' => 'XXL', 'price' => 300],
                    ],
                ],
            ],
        ]);

        /** @var ModelColorDTO $oldColorVariation */
        $oldColorVariation = $oldModel->getColorVariations()->first();

        $article = tap(new Article([
            'is_modno' => $oldColorVariation->getMainArticleNumber(),
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ]), fn($v) => $v->save());

        $newImportFile = tap(
            new ImportFile(['original_filename' => '2.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $newModel = new ModelTest($newImportFile, [
            'name' => $oldModel->getModelName(),
            'number' => $oldModel->getModelNumber(),
            'gln' => $oldModel->getBranches()->first(),
            'colorVariations' => [
                [
                    'colorName' => $oldColorVariation->getColorName(),
                    'colorNumber' => $oldColorVariation->getColorNumber(),
                    'sizeVariations' => $oldColorVariation->getSizeVariations()
                        ->map(function (ModelColorSizeDTO $colorSizeDto) {
                            return [
                                'ean' => $colorSizeDto->getEan(),
                                'size' => $colorSizeDto->getSize(),
                                'price' => $colorSizeDto->getPrice() + 5,
                            ];
                        })
                        ->toArray(),
                ],
            ],
        ]);

        /** @var ModelColorDTO $newColorVariation */
        $newColorVariation = $newModel->getColorVariations()->first();
        /** @var ModelColorSizeDTO $newSizeVariation */
        $newSizeVariation = $newColorVariation->getSizeVariations()->first();

        $productDto = new ProductDTO([
            'id' => $article->sw_product_id,
            'children' => $oldColorVariation->getSizeVariations()
                ->map(function (ModelColorSizeTest $colorSizeDto) use ($glnToImport): array {
                    $netPrice = $colorSizeDto->getPrice() / (1 + ($colorSizeDto->getVatPercentage() / 100));

                    $data = [
                        'id' => (string) Str::uuid()->getHex(),
                        'ean' => $colorSizeDto->getEan(),
                        'stock' => $colorSizeDto->getStockPerBranch()->get($glnToImport),
                        'price' => [
                            [
                                'net' => $netPrice,
                                'gross' => $colorSizeDto->getPrice(),
                                'listPrice' => ['net' => $colorSizeDto->getNetPrice(), 'gross' => $colorSizeDto->getPrice()],
                            ],
                        ],
                        'customFields' => [
                            'sim_protected_price' => true,
                        ],
                    ];

                    if ($colorSizeDto->getSize() === 'L')
                        Arr::forget($data, 'price.0.listPrice');

                    if ($colorSizeDto->getSize() === 'XXL')
                        Arr::forget($data, 'customFields');

                    return $data;
                })
                ->toArray(),
            'configuratorSettings' => $oldColorVariation
                ->getSizeVariations()
                ->map(function (ModelColorSizeDTO $colorSizeDto): array {
                    $optionId = (string) Str::uuid()->getHex();

                    return [
                        'id' => (string) Str::uuid()->getHex(),
                        'optionId' => $optionId,
                        'option' => [
                            'id' => $optionId,
                            'name' => $colorSizeDto->getSize(),
                        ]
                    ];
                })
                ->toArray(),
        ]);

        // mocks
        $shopwareApi = $this->createMock(Shopware6API::class);
        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        $shopwareApi->expects(static::once())
            ->method('getProductById')
            ->with($article->sw_product_id)
            ->willReturn($productDto);

        $shopwareApi->expects(static::once())
            ->method('updateProduct')
            ->with($article->sw_product_id, static::callback($updateProductArgRecorder = new ArgRecorder()));

        $propertyGroupImporter = new PropertyGroupImporterFake();

        // execution
        $modelImporter = $this->createModelImporterWithApi($shopwareApi, propertyGroupImporter: $propertyGroupImporter);
        $modelImporter->setGlnToImport($glnToImport);

        $modelImporter->import($newModel);

        // assertions
        $updateProductData = $updateProductArgRecorder->latest();
        static::assertIsArray($updateProductData);
        static::assertContainsOnly('string', Arr::pluck($updateProductData['children'], 'id'));

        // check that the list price is set if it is not existing, even if price protection is enabled
        [$childA, $childB, $childC] = $updateProductData['children'];
        static::assertNotNull($childA);

        static::assertEquals([
            'listPrice' => [
                'gross' => $newSizeVariation->getPrice(),
                'net' => $newSizeVariation->getNetPrice(),
                'linked' => false,
            ],
            'linked' => false,
        ], current($childA['price']));

        // check that price protection is respected and prices are not touched
        static::assertCount(0, $childB['price']);

        // check list price is updated
        $newSizeVariation = $newColorVariation->getSizeVariations()->firstWhere(fn ($v) => $v->getSize() === 'XXL');
        static::assertEquals(
            ['listPrice' => ['gross' => $newSizeVariation->getPrice(), 'net' => $newSizeVariation->getNetPrice(), 'linked' => false], 'linked' => false],
            current($childC['price']),
        );

        // check active state in variants is always explicitly set to null to retain inheritance
        foreach ($updateProductData['children'] as $child) {
            static::assertArrayHasKey('active', $child);
            static::assertNull($child['active']);
        }
    }

    public function testVariantUpdateFromDeltaIgnoresStock()
    {
        $oldImportFile = tap(
            new ImportFile(['original_filename' => '1.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $oldModel = new ModelTest($oldImportFile, [
            'name' => 'name of kek/alpha-23',
            'number' => 'kek/alpha-23',
            'gln' => $glnToImport = Str::random(13),
            'colorVariations' => [
                [
                    'colorName' => 'black',
                    'colorNumber' => '0x00',
                    'sizeVariations' => [
                        ['size' => 'L', 'price' => 100],
                    ],
                ],
            ],
        ]);

        /** @var ModelColorDTO $oldColorVariation */
        $oldColorVariation = $oldModel->getColorVariations()->first();

        $article = tap(new Article([
            'is_modno' => $oldColorVariation->getMainArticleNumber(),
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ]), fn($v) => $v->save());

        $newImportFile = tap(
            new ImportFile(['original_filename' => '2.csv', 'type' => ImportFile::TYPE_DELTA]),
            fn($v) => $v->save(),
        );

        $newModel = new ModelTest($newImportFile, [
            'name' => $oldModel->getModelName(),
            'number' => $oldModel->getModelNumber(),
            'gln' => $oldModel->getBranches()->first(),
            'colorVariations' => [
                [
                    'colorName' => $oldColorVariation->getColorName(),
                    'colorNumber' => $oldColorVariation->getColorNumber(),
                    'sizeVariations' => $oldColorVariation->getSizeVariations()
                        ->map(function (ModelColorSizeDTO $colorSizeDto) use ($glnToImport) {
                            return [
                                'ean' => $colorSizeDto->getEan(),
                                'size' => $colorSizeDto->getSize(),
                                'price' => $colorSizeDto->getPrice() + 5,
                                'stock' => $colorSizeDto->getStockPerBranch()->get($glnToImport) + 10,
                            ];
                        })
                        ->toArray(),
                ],
            ],
        ]);

        /** @var ModelColorDTO $newColorVariation */
        $newColorVariation = $newModel->getColorVariations()->first();
        /** @var ModelColorSizeDTO $newSizeVariation */
        $newSizeVariation = $newColorVariation->getSizeVariations()->first();

        $productDto = new ProductDTO([
            'id' => $article->sw_product_id,
            'children' => $oldColorVariation->getSizeVariations()
                ->map(function (ModelColorSizeTest $colorSizeDto) use ($glnToImport): array {
                    $netPrice = $colorSizeDto->getPrice() / (1 + ($colorSizeDto->getVatPercentage() / 100));

                    $data = [
                        'id' => (string) Str::uuid()->getHex(),
                        'ean' => $colorSizeDto->getEan(),
                        'stock' => $colorSizeDto->getStockPerBranch()->get($glnToImport),
                        'price' => [
                            [
                                'net' => $netPrice,
                                'gross' => $colorSizeDto->getPrice(),
                                'listPrice' => ['net' => $colorSizeDto->getNetPrice(), 'gross' => $colorSizeDto->getPrice()],
                            ],
                        ],
                        'customFields' => [
                            'sim_protected_price' => true,
                        ],
                    ];

                    if ($colorSizeDto->getSize() === 'L')
                        Arr::forget($data, 'price.0.listPrice');

                    if ($colorSizeDto->getSize() === 'XXL')
                        Arr::forget($data, 'customFields');

                    return $data;
                })
                ->toArray(),
            'configuratorSettings' => $oldColorVariation
                ->getSizeVariations()
                ->map(function (ModelColorSizeDTO $colorSizeDto): array {
                    $optionId = (string) Str::uuid()->getHex();

                    return [
                        'id' => (string) Str::uuid()->getHex(),
                        'optionId' => $optionId,
                        'option' => [
                            'id' => $optionId,
                            'name' => $colorSizeDto->getSize(),
                        ]
                    ];
                })
                ->toArray(),
        ]);

        // mocks
        $shopwareApi = $this->createMock(Shopware6API::class);
        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        $shopwareApi->expects(static::once())
            ->method('getProductById')
            ->with($article->sw_product_id)
            ->willReturn($productDto);

        $shopwareApi->expects(static::once())
            ->method('updateProduct')
            ->with($article->sw_product_id, static::callback($updateProductArgRecorder = new ArgRecorder()));

        $propertyGroupImporter = new PropertyGroupImporterFake();

        // execution
        $modelImporter = $this->createModelImporterWithApi($shopwareApi, propertyGroupImporter: $propertyGroupImporter);
        $modelImporter->setGlnToImport($glnToImport);

        $modelImporter->import($newModel);

        // assertions
        $updateProductData = $updateProductArgRecorder->latest();
        static::assertIsArray($updateProductData);
        static::assertContainsOnly('string', Arr::pluck($updateProductData['children'], 'id'));

        // check that the list price is set if it is not existing, even if price protection is enabled
        [$childA] = $updateProductData['children'];
        static::assertNotNull($childA);
        static::assertArrayNotHasKey('stock', $childA);
    }


    public function testVariantUpdateSizeChange()
    {
        $oldImportFile = tap(
            new ImportFile(['original_filename' => '1.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $oldModel = new ModelTest($oldImportFile, [
            'name' => 'name of kek/alpha-23',
            'number' => 'kek/alpha-23',
            'gln' => $glnToImport = Str::random(13),
            'colorVariations' => [
                [
                    'colorName' => 'black',
                    'colorNumber' => '0x00',
                    'sizeVariations' => [
                        ['size' => 'L'],
                    ],
                ],
            ],
        ]);

        /** @var ModelColorDTO $oldColorVariation */
        $oldColorVariation = $oldModel->getColorVariations()->first();

        $article = tap(new Article([
            'is_modno' => $oldColorVariation->getMainArticleNumber(),
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ]), fn($v) => $v->save());

        $newImportFile = tap(
            new ImportFile(['original_filename' => '2.csv', 'type' => ImportFile::TYPE_BASE]),
            fn($v) => $v->save(),
        );

        $newModel = new ModelTest($newImportFile, [
            'name' => $oldModel->getModelName(),
            'number' => $oldModel->getModelNumber(),
            'gln' => $oldModel->getBranches()->first(),
            'colorVariations' => [
                [
                    'colorName' => $oldColorVariation->getColorName(),
                    'colorNumber' => $oldColorVariation->getColorNumber(),
                    'sizeVariations' => $oldColorVariation->getSizeVariations()
                        ->map(function (ModelColorSizeDTO $colorSizeDto) {
                            return [
                                'ean' => $colorSizeDto->getEan(),
                                'size' => $colorSizeDto->getSize(),
                            ];
                        })
                        ->toArray(),
                ],
            ],
        ]);

        /** @var ModelColorDTO $newColorVariation */
        $newColorVariation = $newModel->getColorVariations()->first();
        /** @var ModelColorSizeDTO $newSizeVariation */
        $newSizeVariation = $newColorVariation->getSizeVariations()->first();

        $productDto = new ProductDTO([
            'id' => $article->sw_product_id,
            'children' => $oldColorVariation->getSizeVariations()
                ->map(function (ModelColorSizeTest $colorSizeDto) use ($glnToImport): array {
                    $sizePropertyGroup = $this->propertyGroupImporter
                        ->import(ModelImporter::PROPERTY_GROUP_NAME_SIZE, [$colorSizeDto->getSize(), 'kekw']);

                    $data = [
                        'id' => (string) Str::uuid()->getHex(),
                        'ean' => $colorSizeDto->getEan(),
                        'stock' => $colorSizeDto->getStockPerBranch()->get($glnToImport),
                        'price' => [
                            [
                                'net' => $colorSizeDto->getNetPrice(),
                                'gross' => $colorSizeDto->getPrice(),
                                'listPrice' => ['net' => $colorSizeDto->getNetPrice(), 'gross' => $colorSizeDto->getPrice()],
                            ],
                        ],
                        'options' => [
                            [
                                'groupId' => $sizePropertyGroup->getId(),
                                'id' => $sizePropertyGroup->getOptionByName($colorSizeDto->getSize())->getId(),
                            ],
                            [
                                'groupId' => $sizePropertyGroup->getId(),
                                'id' => $sizePropertyGroup->getOptionByName('kekw')->getId(),
                            ]
                        ],
                    ];

                    return $data;
                })
                ->toArray(),
            'configuratorSettings' => $oldColorVariation
                ->getSizeVariations()
                ->map(function (ModelColorSizeDTO $colorSizeDto): array {
                    $sizePropertyGroup = $this->propertyGroupImporter
                        ->import(ModelImporter::PROPERTY_GROUP_NAME_SIZE, [$colorSizeDto->getSize()]);

                    $optionId = $sizePropertyGroup->getOptionByName($colorSizeDto->getSize())->getId();

                    return [
                        'id' => (string) Str::uuid()->getHex(),
                        'optionId' => $optionId,
                        'option' => [
                            'id' => $optionId,
                            'name' => $colorSizeDto->getSize(),
                        ]
                    ];
                })
                ->toArray(),
        ]);

        // mocks
        $sizeMapper = $this->createMock(SizeMapper::class);
        $sizeMapper->expects(static::atLeastOnce())
            ->method('mapSize')
            ->with(static::anything())
            ->willReturnCallback(fn (SizeMappingRequest $req): string => "{$req->getSize()}-pog");

        $shopwareApi = $this->createMock(Shopware6API::class);
        $shopwareApi->expects(static::atLeastOnce())
            ->method('searchCurrencyIdByIsoCode')
            ->with('EUR')
            ->willReturn($currencyId = Str::random(32));

        $shopwareApi->expects(static::once())
            ->method('getProductById')
            ->with($article->sw_product_id)
            ->willReturn($productDto);

        $shopwareApi->expects(static::once())
            ->method('updateProduct')
            ->with($article->sw_product_id, static::callback($updateProductArgRecorder = new ArgRecorder()));

        $shopwareApi->expects(static::any())
            ->method('deleteProductVariantOption')
            ->with(
                $article->sw_product_id,
                $productDto->getChildren()[0]['id'],
                static::callback($deleteProductVariantOptionArgRecorder = new ArgRecorder()),
            );

        // execution
        $modelImporter = $this->createModelImporterWithApi(
            $shopwareApi, sizeMapper: $sizeMapper, propertyGroupImporter: $this->propertyGroupImporter
        );
        $modelImporter->setGlnToImport($glnToImport);

        $modelImporter->import($newModel);

        // assertions
        $updateProductData = $updateProductArgRecorder->latest();
        static::assertIsArray($updateProductData);

        // check that configuratorSettings contains the new size
        static::assertIsArray($updateProductData['configuratorSettings']);
        static::assertCount(1, $updateProductData['configuratorSettings']);

        $mappedSizeName = "{$newSizeVariation->getSize()}-pog";
        /** @var PropertyGroupOptionDTO $newSize */
        $newSize = $this->propertyGroupImporter
            ->import(ModelImporter::PROPERTY_GROUP_NAME_SIZE, [$mappedSizeName])
            ->getOptionByName($mappedSizeName);

        static::assertEquals(
            $newSize->getId(),
            Arr::get($updateProductData, 'configuratorSettings.0.optionId'),
        );

        // check that the variant contains the new option
        [$childA] = $updateProductData['children'];
        static::assertContainsOnly('string', Arr::pluck($updateProductData['children'], 'id'));

        static::assertNotNull($childA);
        static::assertEquals(
            [[
                'groupId' => $this->propertyGroupImporter->import(ModelImporter::PROPERTY_GROUP_NAME_SIZE, [])
                    ->getId(),
                'id' => $newSize->getId(),
            ]],
            $childA['options'],
        );

        // check that all other product variant options were deleted
        static::assertEqualsCanonicalizing(
            Arr::pluck($productDto->getChildren()[0]['options'], 'id'),
            $deleteProductVariantOptionArgRecorder->args,
        );
    }

    protected function createModelImporter(): ModelImporter
    {
        return $this->app->make(ModelImporter::class);
    }

    protected function createModelImporterWithApi(
        Shopware6API $shopwareApi,
        ?SizeMapper $sizeMapper = null,
        ?ManufacturerImporter $manufacturerImporter = null,
        ?PropertyGroupImporter $propertyGroupImporter = null,
    ): ModelImporter {
        return new ModelImporter(
            new NullLogger(),
            $shopwareApi,
            $sizeMapper ?? new SizeMapper(),
            $manufacturerImporter ?? new ManufacturerImporterFake(),
            $propertyGroupImporter ?? new PropertyGroupImporterFake(),
        );
    }

    /**
     * @param string $filename
     * @return \Iterator<mixed, ModelDTO>
     */
    protected function readModelsFromFixture(string $filename): \Iterator
    {
        $importFile = $this->createImportFileFromFixture($this->localFS, $filename);
        $reader = new ImportFileReader(new NullLogger(), $this->localFS);

        return $reader($importFile);
    }

    protected function createImportFileFromFixture(Filesystem $fs, string $filename): ImportFile
    {
        $importFile = new ImportFile([
            'type' => ImportFile::TYPE_BASE,
            'original_filename' => $filename,
            'storage_path' => Str::random(40),
        ]);

        $importFile->save();

        $fs->put($importFile->storage_path, Utils::streamFor(fopen(base_path("docs/fixtures/{$filename}"), 'r')));

        return $importFile;
    }

    private function createShopwareApiThatExpectsToNotBeUsed(): Shopware6API
    {
        $api = $this->createMock(Shopware6API::class);

        $api->expects(static::never())
            ->method('findProductByProductNumber')
            ->withAnyParameters();

        $api->expects(static::never())
            ->method('findProductById')
            ->withAnyParameters();

        $api->expects(static::never())
            ->method('createProduct')
            ->withAnyParameters();

        return $api;
    }
}
