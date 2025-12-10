<?php
/**
 * lel since 11.08.18
 */
namespace App\Domain\Import;

use App\Article;
use App\ArticleNumberEanMapping;
use App\Domain\Import\Manufacturer\ManufacturerImporter;
use App\Domain\Import\PropertyGroup\PropertyGroupImporter;
use App\Domain\Import\PropertyGroup\PropertyGroupImporterImpl;
use App\Domain\Shopware6API;
use App\ImportFile;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class ModelImporter
{
    const PROPERTY_GROUP_NAME_SIZE = 'Größe';

    protected LoggerInterface $logger;

    protected Shopware6API $shopwareAPI;

    protected SizeMapper $sizeMapper;

    protected ManufacturerImporter $manufacturerImporter;

    protected PropertyGroupImporter $propertyGroupImporter;

    protected ?string $glnToImport = null;

    protected ?string $shopwareWarehouseCode = null;

    // todo check if still needed
    protected bool $ignoreStockUpdatesFromDelta = false;

    public function __construct(
        LoggerInterface $logger,
        Shopware6API $shopwareAPI,
        SizeMapper $sizeMapper,
        ManufacturerImporter $manufacturerImporter,
        PropertyGroupImporter $propertyGroupImporter,
    ) {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
        $this->sizeMapper = $sizeMapper;
        $this->manufacturerImporter = $manufacturerImporter;
        $this->propertyGroupImporter = $propertyGroupImporter;
    }

    public function setGlnToImport(string $branchToImport): self
    {
        $this->glnToImport = $branchToImport;

        return $this;
    }

    public function setShopwareWarehouseCode(string $shopwareWarehouseCode): self
    {
        $this->shopwareWarehouseCode = $shopwareWarehouseCode;

        return $this;
    }

    public function import(ModelDTO $baseModelData): void
    {
        foreach ($baseModelData->getColorVariations() as $modelData) {
            try {
                $this->importArticle($modelData);
            } catch (UnknownArticleInShopwareException $e) {
                $this->handleUnknownArticleInShopwareException($e);
            }
        }
    }

    protected function importArticle(ModelColorDTO $model): void
    {
        if (!$this->isEligibleForImport($model)) {
            $this->logger->info(__METHOD__ . ' Article is not eligible for import', [
                'sourceFilename' => $model->getImportFile()->original_filename,
                'modNo' => $model->getModelNumber(),
                'colNo' => $model->getColorNumber(),
            ]);

            return;
        }

        $article = $this->tryToFetchShopwareArticle($model);

        if ($article && !$this->isNewImportFileForArticle($model->getImportFile(), $article)) {
            $this->logger->info(__METHOD__ . ' Already imported the same or newer data for the article', [
                'sourceFilename' => $model->getImportFile()->original_filename,
                'modNo' => $model->getModelNumber(),
                'colNo' => $model->getColorNumber(),
                'article.id' => $article->id,
            ]);
        } else {
            $article = $article
                ? $this->updateArticle($article, $model)
                : $this->createArticle($model);
        }

        $article->imports()->create(['import_file_id' => $model->getImportFile()->id]);
    }

    protected function isEligibleForImport(ModelDTO $model): bool
    {
        $branches = $model->getBranches();

        return $branches
            ->first([$this, 'isGlnEligible']) !== null;
    }

    public function isGlnEligible(string $gln): bool
    {
        return $gln === $this->glnToImport;
    }

    protected function tryToFetchShopwareArticle(ModelColorDTO $model): ?Article
    {
        $loggingContext = ['articleNumber' => $model->getMainArticleNumber()];
        $this->logger->info(__METHOD__, $loggingContext);

        $article = Article::query()->where('is_modno', $model->getMainArticleNumber())->first();
        if ($article) return $article;

        $swProduct = $this->shopwareAPI->findProductByProductNumber($model->getMainArticleNumber());
        if (!$swProduct) return null;

        $article = new Article([
            'is_modno' => $model->getMainArticleNumber(),
            'sw_product_id' => $swProduct->getId(),
            'is_active' => true,
        ]);

        $article->save();

        return $article;
    }

    protected function updateArticle(
        Article $article,
        ModelColorDTO $model,
    ): Article {
        $articleNumber = $article->is_modno;
        $swProductId = $article->sw_product_id;
        $swProduct = $this->shopwareAPI->getProductById($swProductId);

        if (!$swProduct) {
            throw new UnknownArticleInShopwareException($article);
        }

        $loggingContext = [
            'articleNumber' => $articleNumber,
            'swProductId' => $swProductId,
            'importFile' => [
                'id' => $model->getImportFile()->id,
                'type' => $model->getImportFile()->type,
            ],
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariants($model, $swProduct);
        [$existingVariants, $newVariants] = $variants->partition('id', '!=', null);

        $variantOptionIds = $variants->flatMap(fn($v) => collect($v['options'])->pluck('id'));
        $existingConfiguratorSettingsOptionIds = collect($swProduct->getConfiguratorSettings())->pluck('optionId');
        $newVariantOptionIds = $variantOptionIds->diff($existingConfiguratorSettingsOptionIds);

        $price = $swProduct->getPrice();
        $newPrice = $this->generateShopwareSimplePriceInfo($model->getSizeVariations()->first());

        if ($swProduct->isMissingListPrice() || !$swProduct->isPriceProtected())
            $price['listPrice'] = $newPrice['listPrice'];

        $updateData = [
            'id' => $swProductId,
            'isCloseout' => true,
            // only already existing variants can be included in the update request of the parent article (sw api restriction)
            'children' => $existingVariants->map(fn (array $v): array => Arr::except($v, ['stock']))->toArray(),
            'configuratorSettings' => $newVariantOptionIds
                ->map(fn(string $optionId): array => ['optionId' => $optionId])
                ->toArray(),
            'deliveryTimeId' => $this->fetchShopwareDeliveryTimeId(),
            'price' => [$price],
        ];

        $this->logger->info(__METHOD__ . ' Updating article', [...$loggingContext, 'updateData' => $updateData]);
        $this->shopwareAPI->updateProduct($swProductId, $updateData);

        if (!$model->getImportFile()->isDelta())
            $this->updateProductVariantStocks($existingVariants, $swProduct, $loggingContext);

        foreach ($newVariants as $newVariant) {
            $this->logger->info(__METHOD__ . ' Creating new variant', [...$loggingContext, 'newVariant' => $newVariant]);
            $this->shopwareAPI->createProduct([
                ...$newVariant,
                'parentId' => $swProductId,
            ]);
        }

        $this->deleteProductVariantOptions($variants, $swProduct, $swProductId);

        return $article;
    }

    protected function createArticle(
        ModelColorDTO $model,
    ): Article {
        $loggingContext = [
            'articleNumber' => $model->getMainArticleNumber(),
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $manufacturer = $this->manufacturerImporter->import($model->getManufacturerName());

        $variants = $this->generateVariants($model);

        /** @var ModelColorSizeDTO $firstVariant */
        $firstVariant = $model->getSizeVariations()->first();

        $productData = [
            'active' => false,
            'isCloseout' => true,
            'name' => $model->getModelName() . ' (' . $model->getColorName() . ')',
            'productNumber' => $model->getMainArticleNumber(),
            'stock' => $firstVariant->getStockPerBranch()->get($this->glnToImport, 0),
            'taxId' => $this->fetchShopwareTaxIdByVatPercentage($model->getVatPercentage()),
            'manufacturerId' => $manufacturer->getId(),
            'price' => [
                $this->generateShopwareSimplePriceInfo($firstVariant),
            ],
            'weight' => Article::DEFAULTS_WEIGHT_KG,
            'children' => $variants,
            'configuratorSettings' => [
                ...$variants->flatMap(fn (array $variant): array => Arr::pluck($variant['options'], 'id'))
                    ->map(fn (string $v): array => ['optionId' => $v]),
            ],
            'deliveryTimeId' => $this->fetchShopwareDeliveryTimeId(),
        ];

        $product = $this->shopwareAPI->createProduct($productData);

        $article = new Article();
        $article->is_modno = $model->getMainArticleNumber();
        $article->sw_product_id = $product->getId();
        $article->is_active = true;
        $article->save();

        return $article;
    }


    protected function generateVariants(
        ModelColorDTO $model,
        ?ProductDTO $productDto = null,
    ): Collection {
        // todo avoid double size mapping
        $mappedSizes = $model->getSizeVariations()
            ->map(fn (ModelColorSizeDTO $model): string => $this->mapSize($model));

        $swPropertyGroup = $this->propertyGroupImporter->import(self::PROPERTY_GROUP_NAME_SIZE, $mappedSizes->toArray());

        $variants = $model->getSizeVariations()
            ->map(function (ModelColorSizeDTO $model) use ($productDto, $swPropertyGroup) {
                $mappedSize = $this->mapSize($model);
                $isVariantUpdate = $productDto && ($productChild = $productDto->getChildByEan($model->getEan()));

                $variantData = [
                    'productNumber' => $model->getVariantArticleNumber(),
                    'ean' => $model->getEan(),
                    'stock' => $model->getStockPerBranch()->get($this->glnToImport, 0),
                    'options' => [
                        [
                            'groupId' => $swPropertyGroup->getId(),
                            'id' => $swPropertyGroup->getOptionByName($mappedSize)->getId(),
                        ],
                    ],
                ];

                if ($isVariantUpdate) {
                    $variantData['id'] = $productChild['id'];
                }

                return $variantData;
            })
            ->filter()
            ->values();

        return $variants;
    }

    protected function mapSize(
        ModelColorSizeDTO $model,
    ): string {
        $variantArticleNumber = $model->getVariantArticleNumber();

        $req = new SizeMappingRequest(
            manufacturerName: $model->getManufacturerName(),
            mainArticleNumber: $model->getMainArticleNumber(),
            variantArticleNumber: $variantArticleNumber,
            size: $model->getSize(),
            targetGroupGender: $model->getTargetGroupGender(),
        );

        return $this->sizeMapper->mapSize($req);
    }

    protected function isNewImportFileForArticle(ImportFile $importFile, Article $article): bool
    {
        if ($article->imports()->where('import_file_id', $importFile->id)->exists()) return false;

        $importFilenames = $article->imports()
            ->join('import_files', 'import_files.id', '=', 'article_imports.import_file_id')
            ->orderBy('article_imports.created_at')
            ->limit(5)
            ->pluck('import_files.original_filename');

        $sanitizeFn = fn (string $v): string => Str::after($v, '-');
        $importFilenames = $importFilenames->map($sanitizeFn);
        $testImportFilename = $sanitizeFn($importFile->original_filename);

        $containsNewerImportFiles = $importFilenames->contains(fn (string $v): bool => $v > $testImportFilename);

        return !$containsNewerImportFiles;
    }

    private function handleUnknownArticleInShopwareException(UnknownArticleInShopwareException $e): void
    {
        $article = $e->getArticle();

        $this->logger->warning('Article that should exist in shopware could not be found. Deleting local record', [
            'articleNumber' => $article->is_modno,
            'swArticleId' => $article->sw_article_id,
        ]);

        $article->delete();
    }

    private function fetchShopwareCurrencyIdByIsoCode(string $isoCode): string
    {
        if ($currencyId = cache()->get("sw-currency-id:{$isoCode}"))
            return $currencyId;

        if ($currencyId = $this->shopwareAPI->searchCurrencyIdByIsoCode($isoCode)) {
            cache()->set("sw-currency-id:{$isoCode}", $currencyId);

            return $currencyId;
        }

        throw new MissingShopwareEntityException('currency', 'isoCode', $isoCode);
    }

    private function fetchShopwareTaxIdByVatPercentage(float $vatPercentage): string
    {
        if ($taxId = cache()->get("sw-tax-id:{$vatPercentage}"))
            return $taxId;

        if ($taxId = $this->shopwareAPI->searchTaxIdByVatPercentage($vatPercentage)) {
            cache()->set("sw-tax-id:{$vatPercentage}", $taxId);

            return $taxId;
        }

        throw new MissingShopwareEntityException('tax', 'taxRate', $vatPercentage);
    }

    private function generateShopwareSimplePriceInfo(ModelColorSizeDTO $model): array
    {
        $netPrice = $model->getPrice() / (1 + $model->getVatPercentage() / 100);

        return [
            'gross' => $model->getPrice(),
            'net' => $netPrice,
            'linked' => false,
            'currencyId' => $this->fetchShopwareCurrencyIdByIsoCode($model->getCurrencyIsoCode()),
            'listPrice' => [
                'gross' => $model->getPrice(),
                'net' => $netPrice,
                'linked' => false,
            ],
        ];
    }

    private function fetchShopwareDeliveryTimeId(): string
    {
        if ($deliveryTimeId = cache()->get("sw-delivery-time-id:0-0"))
            return $deliveryTimeId;

        if ($deliveryTimeId = $this->shopwareAPI->searchDeliveryTimeIdByMinMax(0, 0)) {
            cache()->set("sw-delivery-time-id:0-0", $deliveryTimeId);

            return $deliveryTimeId;
        }

        throw new MissingShopwareEntityException('deliveryTime', 'minMax', '0-0');
    }

    private function fetchShopwareWarehouseId(): string
    {
        $cacheKey = "sw-warehouse-id:{$this->shopwareWarehouseCode}";
        if ($warehouseId = cache()->get($cacheKey))
            return $warehouseId;

        if ($warehouseId = $this->shopwareAPI->searchWarehouseIdByCode($this->shopwareWarehouseCode)) {
            cache()->set($cacheKey, $warehouseId);

            return $warehouseId;
        }

        throw new MissingShopwareEntityException('warehouse', 'code', $this->shopwareWarehouseCode);
    }

    private function deleteProductVariantOptions(Collection $variants, ProductDTO $swProduct, string $swProductId): void
    {
        foreach ($variants as $variant) {
            $oldVariant = $swProduct->getChildByEan($variant['ean']);
            if (!$oldVariant) continue;

            $oldOptionIds = Arr::pluck($oldVariant['options'] ?? [], 'id');
            $newOptionIds = Arr::pluck($variant['options'], 'id');

            $optionIdsToDelete = array_diff($oldOptionIds, $newOptionIds);

            foreach ($optionIdsToDelete as $optionId) {
                $this->shopwareAPI->deleteProductVariantOption($swProductId, $variant['id'], $optionId);
            }
        }
    }

    private function updateProductVariantStocks(Collection $variants, ProductDTO $swProduct, array $loggingContext): void
    {
        foreach ($variants as $existingVariant) {
            $oldStock = $swProduct->getStockByEanAndWarehouseId(
                $existingVariant['ean'],
                $this->fetchShopwareWarehouseId()
            );
            $newStock = $existingVariant['stock'];

            $this->logger->info(
                __METHOD__,
                [...$loggingContext, 'ean' => $existingVariant['ean'], 'oldStock' => $oldStock, 'newStock' => $newStock]
            );

            $stockChange = $newStock - $oldStock;
            if ($stockChange === 0) continue;

            $this->shopwareAPI->updateProductWarehouseStock(
                $this->fetchShopwareWarehouseId(),
                $swProduct->getChildByEan($existingVariant['ean'])['id'],
                $newStock - $oldStock,
            );
        }
    }
}
