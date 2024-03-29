<?php
/**
 * lel since 11.08.18
 */
namespace App\Domain\Import;

use App\Article;
use App\ArticleNumberEanMapping;
use App\Domain\ShopwareAPI;
use App\ImportFile;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class ModelImporter
{
    protected LoggerInterface $logger;

    protected ShopwareAPI $shopwareAPI;

    protected SizeMapper $sizeMapper;

    protected ?string $glnToImport = null;

    protected array $glnBranchMapping = [];

    protected bool $ignoreStockUpdatesFromDelta = false;

    /**
     * ModelXMLImporter constructor.
     * @param LoggerInterface $logger
     * @param ShopwareAPI $shopwareAPI
     * @param SizeMapper $sizeMapper
     */
    public function __construct(
        LoggerInterface $logger,
        ShopwareAPI $shopwareAPI,
        SizeMapper $sizeMapper,
    ) {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
        $this->sizeMapper = $sizeMapper;
    }

    public function setGlnToImport(string $branchToImport): self
    {
        $this->glnToImport = $branchToImport;

        return $this;
    }

    public function setGlnBranchMapping(array $glnBranchMapping): self
    {
        $this->glnBranchMapping = $glnBranchMapping;

        return $this;
    }

    public function setIgnoreStockUpdatesFromDelta(bool $ignore): self
    {
       $this->ignoreStockUpdatesFromDelta = $ignore;

       return $this;
    }

    public function import(ModelDTO $baseModelData): void
    {
        foreach ($baseModelData->getColorVariations() as $modelData) {
            try {
                $this->importArticle($modelData);
            } catch (UnknownArticleInShopwareException $e) {
                $this->handleUnknownArticleInShopwareException($e);
            } catch (Exception $e) {
                $this->logger->warning('Failed to import article', [
                    'e' => $e->getMessage(),
                ]);

                report($e);
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

        $eans = $model->getSizeVariations()->map(fn (ModelColorSizeDTO $model): string => $model->getEan());
        $article = Article::query()
            ->whereHas('numberEanMappings', fn (Builder $query) => $query->whereIn('ean', $eans))
            ->first();

        if ($article) return $article;

        $swArticleId = $this->shopwareAPI->searchShopwareArticleIdByArticleNumber($model->getMainArticleNumber());
        if (!$swArticleId) return null;

        $article = new Article([
            'is_modno' => $model->getMainArticleNumber(),
            'sw_article_id' => $swArticleId,
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
        $swArticleId = $article->sw_article_id;
        $swArticleInfo = $this->shopwareAPI->searchShopwareArticleInfoByArticle($article);

        if (!$swArticleInfo) {
            throw new UnknownArticleInShopwareException($article);
        }

        $loggingContext = [
            'articleNumber' => $articleNumber,
            'swArticleId' => $swArticleId,
            'foundSWArticleInfo' => !is_null($swArticleInfo),
            'importFile' => [
                'id' => $model->getImportFile()->id,
                'type' => $model->getImportFile()->type,
            ],
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariants($model, $swArticleInfo)
            ->map(function ($variant) use ($model, $swArticleInfo) {
                $variant['attribute'] = Arr::only($variant['attribute'], ['availability']);

                // always include prices and stock information if the variant is new
                if ($swArticleInfo->variantExists($variant['number'])) {
                    unset($variant['lastStock']);

                    if ($swArticleInfo->isPriceProtected($variant['number']))
                        unset($variant['prices']);

                    if ($this->ignoreStockUpdatesFromDelta
                        && $model->getImportFile()->type === ImportFile::TYPE_DELTA)
                        unset($variant['inStock']);
                }

                return $variant;
            });

        $loggingContext['variants'] = $variants->toArray();
        $firstVariant = $variants->first(fn (array $variant): bool => !is_null($variant['prices'] ?? null));
        $pricesOfTheFirstVariant = $firstVariant['prices'] ?? null;

        $mainDetail = [];
        if (!$swArticleInfo->isPriceProtected($articleNumber) && !is_null($pricesOfTheFirstVariant))
            $mainDetail['prices'] = $pricesOfTheFirstVariant;

        $articleData = [
            'mainDetail' => $mainDetail,
            'configuratorSet' => [
                'type' => 2,
                'groups' => $this->createConfiguratorSetGroupsFromVariants($variants),
            ],
            'variants' => $variants,
        ];

        if ($model->getImportFile()->type === ImportFile::TYPE_DELTA)
            unset($articleData['configuratorSet']);

        $this->shopwareAPI->updateShopwareArticle($swArticleId, $articleData);

        $this->updateEanMappings($article, $variants);

        $this->logger->info(__METHOD__ . ' Updated Article', $loggingContext);

        return $article;
    }

    protected function updateEanMappings(Article $article, iterable $variants): void
    {
        foreach ($variants as $variant) {
            if (empty(trim($variant['ean'])))
                continue;

            ArticleNumberEanMapping::query()->firstOrCreate(
                [
                    'article_id' => $article->id,
                    'ean' => $variant['ean'],
                ],
                [
                    'article_number' => $variant['number'],
                ],
            );
        }
    }

    protected function createArticle(
        ModelColorDTO $model,
    ): Article {
        $loggingContext = [
            'articleNumber' => $model->getMainArticleNumber(),
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariants($model);
        $pricesOfTheFirstVariant = data_get($variants, '0.prices');

        $articleData = [
            'active' => false,
            'name' => $model->getModelName() . ' (' . $model->getColorName() . ')',
            'tax' => number_format($model->getVatPercentage(), 2),
            'supplier' => $model->getManufacturerName(),
            'lastStock' => true,
            'mainDetail' => [
                'number' => $model->getMainArticleNumber(),
                'prices' => $pricesOfTheFirstVariant,
                'weight' => Article::DEFAULTS_WEIGHT,
                'shippingTime' => Article::DEFAULTS_SHIPPING_TIME,
            ],
            'configuratorSet' => [
                'type' => 2, // variant display type picture
                'groups' => $this->createConfiguratorSetGroupsFromVariants($variants),
            ],
            'variants' => $variants,
        ];

        $swArticleId = $this->shopwareAPI->createShopwareArticle($articleData);

        $article = new Article();
        $article->is_modno = $model->getMainArticleNumber();
        $article->sw_article_id = $swArticleId;
        $article->is_active = true;
        $article->save();

        $this->createEanMappings($article, $variants);

        $this->logger->info(__METHOD__ . ' Post Articles Response ', $loggingContext);

        return $article;
    }

    protected function createEanMappings(Article $article, iterable $variants): void
    {
        $eanMappings = Collection::make($variants)
            ->filter(fn (array $variant): bool => !empty(trim($variant['ean'])))
            ->map(fn (array $variant): ArticleNumberEanMapping => new ArticleNumberEanMapping([
                'ean' => $variant['ean'],
                'article_number' => $variant['number'],
            ]));

        $article->numberEanMappings()->saveMany($eanMappings);
    }

    protected function generateVariants(
        ModelColorDTO $model,
        ?ShopwareArticleInfo $swArticleInfo = null,
    ): Collection {
        $variants = $model->getSizeVariations()
            ->map(function (ModelColorSizeDTO $model) use ($swArticleInfo) {
                $eligibleBranches = $model->getBranches()->filter([$this, 'isGlnEligible']);
                $eligibleBranch = $eligibleBranches->first();

                $variantArticleNumber = $this->fetchVariantArticleNumber($model);

                $mappedSize = $this->mapSize($model, $variantArticleNumber);

                $variantData = [
                    'active' => true,
                    'number' => $variantArticleNumber,
                    'ean' => $model->getEan(),
                    'lastStock' => true,
                ];

                if ($swArticleInfo && $swArticleInfo->variantExists($variantData['number']))
                    unset($variantData['active']);

                $stockPerBranch = $model->getStockPerBranch();
                $availability = $this->mergeAvailabilityInfo(
                    collect($swArticleInfo ? $swArticleInfo->getAvailabilityInfo($variantData['number']) : []),
                    $stockPerBranch
                        ->map(fn (int $stock, string $branch): array => [
                            'branchNo' => $this->mapGlnToBranchNo($branch),
                            'stock' => $stock,
                        ])
                        ->values()
                );

                if (!$eligibleBranch) {
                    if (!$swArticleInfo) return null;

                    if (!$swArticleInfo->variantExists($variantData['number'])) return null;
                } else {
                    $variantData = array_merge($variantData, [
                        'prices' => [[
                            'price' => $model->getPrice(),
                            'pseudoPrice' => $model->getPseudoPrice(),
                        ]],
                        'inStock' => $stockPerBranch[$eligibleBranch],
                    ]);
                }

                $variantData = array_merge($variantData, [
                    'attribute' => [
                        'attr1' => $model->getVariantName(),
                        'availability' => json_encode($availability),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => $mappedSize],
                    ],
                ]);

                return $variantData;
            })
            ->filter()
            ->values();

        return $variants;
    }

    protected function fetchVariantArticleNumber(ModelColorSizeDTO $model): string
    {
        if (empty($ean = $model->getEan())) {
            return $model->getVariantArticleNumber();
        }

        /** @var ArticleNumberEanMapping $mapping */
        $mapping = ArticleNumberEanMapping::query()->where(['ean' => $ean])->first();

        return $mapping
            ? $mapping->article_number
            : $model->getVariantArticleNumber();
    }

    protected function mapSize(
        ModelColorSizeDTO $model,
        string $variantArticleNumber,
    ): string {
        $req = new SizeMappingRequest(
            manufacturerName: $model->getManufacturerName(),
            mainArticleNumber: $model->getMainArticleNumber(),
            variantArticleNumber: $variantArticleNumber,
            size: $model->getSize(),
            targetGroupGender: $model->getTargetGroupGender(),
        );

        return $this->sizeMapper->mapSize($req);
    }

    protected function mergeAvailabilityInfo(Enumerable $existing, Enumerable $new): Enumerable
    {
        $merged = $existing->keyBy('branchNo')
            ->merge($new->keyBy('branchNo'));

        return $merged->values();
    }

    /**
     * @param $variants
     * @return mixed
     */
    protected function createConfiguratorSetGroupsFromVariants($variants)
    {
        return $variants->pluck('configuratorOptions')
            ->reduce(function (Collection $acc, $configuratorOptions) {
                foreach ($configuratorOptions as $configuratorOption) {
                    $group = $configuratorOption['group'];
                    $option = $configuratorOption['option'];

                    if (!$acc->has($group)) $acc->put($group, collect());
                    $acc->get($group)->push($option);
                }

                return $acc;
            }, collect())
            ->map(function ($groupOptions, $groupName) {
                return [
                    'name' => $groupName,
                    'options' => $groupOptions->unique()
                        ->map(function ($option) {
                            return ['name' => $option];
                        })
                        ->values()
                ];
            })
            ->values();
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

    private function mapGlnToBranchNo(string $gln): string
    {
        return $this->glnBranchMapping[$gln] ?? $gln;
    }
}
