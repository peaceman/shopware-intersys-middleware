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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

class ModelXMLImporter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ShopwareAPI
     */
    protected $shopwareAPI;

    /**
     * @var SizeMapper
     */
    protected $sizeMapper;

    /**
     * @var string[]
     */
    protected $branchesToImport = [];

    protected bool $ignoreStockUpdatesFromDelta = false;

    /**
     * ModelXMLImporter constructor.
     * @param LoggerInterface $logger
     * @param ShopwareAPI $shopwareAPI
     * @param SizeMapper $sizeMapper
     */
    public function __construct(LoggerInterface $logger, ShopwareAPI $shopwareAPI, SizeMapper $sizeMapper)
    {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
        $this->sizeMapper = $sizeMapper;
    }

    /**
     * @param string[] $branchesToImport
     */
    public function setBranchesToImport(array $branchesToImport)
    {
        $this->branchesToImport = $branchesToImport;
    }

    public function setIgnoreStockUpdatesFromDelta(bool $ignore): self
    {
       $this->ignoreStockUpdatesFromDelta = $ignore;

       return $this;
    }

    public function import(ModelXMLData $modelXMLData): void
    {
        $modelXML = $modelXMLData->getSimpleXMLElement();

        $articleNodes = $modelXML->xpath('/Model/Color');
        foreach ($articleNodes as $articleNode) {
            try {
                $this->importArticle($modelXMLData, $modelXML, $articleNode);
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

    protected function importArticle(ModelXMLData $modelXMLData, SimpleXMLElement $modelNode, SimpleXMLElement $articleNode)
    {
        $modNo = (string)$modelNode->Modno;
        $colNo = (string)$articleNode->Colno;

        if (!$this->isEligibleForImport($articleNode)) {
            $this->logger->info(__METHOD__ . ' Article is not eligible for import', [
                'sourceFilename' => $modelXMLData->getImportFile()->original_filename,
                'modNo' => $modNo,
                'colNo' => $colNo,
            ]);

            return;
        }

        $articleNumber = $modNo . $colNo;
        $article = $this->tryToFetchShopwareArticle($articleNumber);

        if ($article && !$this->isNewImportFileForArticle($modelXMLData->getImportFile(), $article)) {
            $this->logger->info(__METHOD__ . ' Already imported the same or newer data for the article', [
                'sourceFilename' => $modelXMLData->getImportFile()->original_filename,
                'modNo' => $modNo,
                'colNo' => $colNo,
                'article.id' => $article->id,
            ]);
        } else {
            $article = $article
                ? $this->updateArticle($modelXMLData, $article, $modelNode, $articleNode)
                : $this->createArticle($articleNumber, $modelNode, $articleNode);
        }

        $article->imports()->create(['import_file_id' => $modelXMLData->getImportFile()->id]);
    }

    protected function isEligibleForImport(SimpleXMLElement $articleNode): bool
    {
        $branches = $articleNode->xpath('Size/Branch');

        return collect($branches)
            ->first([$this, 'isBranchEligible']) !== null;
    }

    public function isBranchEligible(SimpleXMLElement $branchXML): bool
    {
        return in_array($branchXML->Branchno ?? null, $this->branchesToImport);
    }

    protected function tryToFetchShopwareArticle(string $articleNumber): ?Article
    {
        $loggingContext = ['articleNumber' => $articleNumber];
        $this->logger->info(__METHOD__, $loggingContext);

        $article = Article::query()->where('is_modno', $articleNumber)->first();
        if ($article) return $article;

        $swArticleId = $this->shopwareAPI->searchShopwareArticleIdByArticleNumber($articleNumber);
        if (!$swArticleId) return null;

        $article = new Article([
            'is_modno' => $articleNumber,
            'sw_article_id' => $swArticleId,
            'is_active' => true,
        ]);

        $article->save();

        return $article;
    }

    protected function updateArticle(
        ModelXMLData $modelXMLData,
        Article $article,
        SimpleXMLElement $modelNode,
        SimpleXMLElement $articleNode
    )
    {
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
                'id' => $modelXMLData->getImportFile()->id,
                'type' => $modelXMLData->getImportFile()->type,
            ],
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariants($modelNode, $articleNode, $swArticleInfo)
            ->map(function ($variant) use ($swArticleInfo, $modelXMLData) {
                $variant['attribute'] = Arr::only($variant['attribute'], ['availability']);
                unset($variant['lastStock']);

                // always include prices and stock information if the variant is new
                if ($swArticleInfo->variantExists($variant['number'])) {
                    if ($swArticleInfo->isPriceProtected($variant['number']))
                        unset($variant['prices']);

                    if ($this->ignoreStockUpdatesFromDelta
                        && $modelXMLData->getImportFile()->type === ImportFile::TYPE_DELTA)
                        unset($variant['inStock']);
                }

                return $variant;
            });

        $loggingContext['variants'] = $variants->toArray();
        $firstVariant = $variants->first(function ($variant) { return !is_null($variant['prices'] ?? null); });
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

        if ($modelXMLData->getImportFile()->type === ImportFile::TYPE_DELTA)
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
        string $articleNumber,
        SimpleXMLElement $modelNode,
        SimpleXMLElement $articleNode
    ): Article
    {
        $loggingContext = [
            'articleNumber' => $articleNumber,
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariants($modelNode, $articleNode);
        $pricesOfTheFirstVariant = data_get($variants, '0.prices');

        $articleData = [
            'active' => false,
            'name' => (string)$modelNode->Moddeno . ' (' . (string)$articleNode->Colordeno . ')',
            'tax' => (string)$modelNode->Percentvat,
            'supplier' => (string)$modelNode->Branddeno,
            'descriptionLong' => (string)$modelNode->Longdescription,
            'lastStock' => true,
            'mainDetail' => [
                'number' => $articleNumber,
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
        $article->is_modno = $articleNumber;
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
        SimpleXMLElement $modelNode, SimpleXMLElement $articleNode, ?ShopwareArticleInfo $swArticleInfo = null
    ): Collection
    {
        $variants = collect($articleNode->xpath('Size'))
            ->map(function (SimpleXMLElement $sizeXML) use ($swArticleInfo, $modelNode, $articleNode) {
                $branches = collect($sizeXML->xpath('Branch'));
                $eligibleBranches = $branches->filter([$this, 'isBranchEligible']);
                $eligibleBranch = $eligibleBranches->values()->first();

                $mappedSize = $this->mapSize($modelNode, $articleNode, $sizeXML);

                $variantData = [
                    'active' => true,
                    'number' => (string)$sizeXML->Itemno,
                    'ean' => (string)$sizeXML->Ean,
                    'lastStock' => true,
                ];

                if ($swArticleInfo && $swArticleInfo->variantExists($variantData['number']))
                    unset($variantData['active']);

                $availability = $this->mergeAvailabilityInfo(
                    collect($swArticleInfo ? $swArticleInfo->getAvailabilityInfo($variantData['number']) : []),
                    $this->generateAvailabilityAttributeFromBranches($branches)
                );

                if (!$eligibleBranch) {
                    if (!$swArticleInfo) return null;

                    if (!$swArticleInfo->variantExists($variantData['number'])) return null;
                } else {
                    $variantData = array_merge($variantData, [
                        'prices' => [[
                            'price' => (float)$eligibleBranch->Saleprice,
                            'pseudoPrice' => $eligibleBranch->Xprice ? (float)$eligibleBranch->Xprice : null,
                        ]],
                        'inStock' => (int)$eligibleBranch->Stockqty,
                    ]);
                }

                $variantData = array_merge($variantData, [
                    'attribute' => [
                        'attr1' => (string)$sizeXML->Itemdeno,
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

    protected function mapSize(
        SimpleXMLElement $modelNode,
        SimpleXMLElement $articleNode,
        SimpleXMLElement $sizeXML
    ): string {
        $req = new SizeMappingRequest(
            manufacturerName: (string) $modelNode->Branddeno,
            mainArticleNumber: (string) $modelNode->Modno . (string) $articleNode->Colno,
            variantArticleNumber: (string) $sizeXML->Itemno,
            size: (string) $sizeXML->Sizedeno,
            fedas: (string) $modelNode->Fedas,
        );

        return $this->sizeMapper->mapSize($req);
    }

    protected function generateAvailabilityAttributeFromBranches(Collection $branches): Collection
    {
        return $branches
            ->map(function (SimpleXMLElement $branchXML) {
                return [
                    'branchNo' => (string)$branchXML->Branchno,
                    'stock' => (int)$branchXML->Stockqty,
                ];
            })
            ->values();
    }

    protected function mergeAvailabilityInfo(Collection $existing, Collection $new): Collection
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

        return $article->imports()
            ->whereHas('importFile', function ($q) use ($importFile) {
                $q->where('original_filename', '>=', $importFile->original_filename);
            })
            ->doesntExist();
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
}
