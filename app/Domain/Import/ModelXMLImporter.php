<?php
/**
 * lel since 11.08.18
 */
namespace App\Domain\Import;

use App\Article;
use App\Domain\ShopwareAPI;
use App\Domain\SizeMapper;
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

    public function import(ModelXMLData $modelXMLData): void
    {
        $modelXML = $modelXMLData->getSimpleXMLElement();

        $articleNodes = $modelXML->xpath('/Model/Color');
        foreach ($articleNodes as $articleNode) {
            $this->importArticle($modelXMLData, $modelXML, $articleNode);
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
                ? $this->updateArticle($article, $modelNode, $articleNode)
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
        Article $article,
        SimpleXMLElement $modelNode,
        SimpleXMLElement $articleNode
    )
    {
        $articleNumber = $article->is_modno;
        $swArticleId = $article->sw_article_id;
        $swArticleInfo = $this->shopwareAPI->searchShopwareArticleInfoByArticle($article);

        $loggingContext = [
            'articleNumber' => $articleNumber,
            'swArticleId' => $swArticleId,
            'foundSWArticleInfo' => !is_null($swArticleInfo),
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariants($modelNode, $articleNode)
            ->map(function ($variant) use ($swArticleInfo) {
                $variant['attribute'] = array_only($variant['attribute'], ['availability']);
                unset($variant['active']);
                unset($variant['lastStock']);

                if ($swArticleInfo->isPriceProtected($variant['number']))
                    unset($variant['prices']);

                return $variant;
            });

        $pricesOfTheFirstVariant = data_get($variants, '0.prices');

        $mainDetail = [];
        if (!$swArticleInfo->isPriceProtected($articleNumber))
            $mainDetail['prices'] = $pricesOfTheFirstVariant;

        $articleData = [
            'mainDetail' => $mainDetail,
            'configuratorSet' => [
                'type' => 2,
                'groups' => $this->createConfiguratorSetGroupsFromVariants($variants),
            ],
            'variants' => $variants,
        ];

        $this->shopwareAPI->updateShopwareArticle($swArticleId, $articleData);

        $this->logger->info(__METHOD__ . ' Updated Article', $loggingContext);

        return $article;
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

        $this->logger->info(__METHOD__ . ' Post Articles Response ', $loggingContext);

        return $article;
    }

    protected function generateVariants(SimpleXMLElement $modelNode, SimpleXMLElement $articleNode): Collection
    {
        $variants = collect($articleNode->xpath('Size'))
            ->map(function (SimpleXMLElement $sizeXML) use ($modelNode, $articleNode) {
                [$eligibleBranches, $nonEligibleBranches] = collect($sizeXML->xpath('Branch'))
                    ->partition([$this, 'isBranchEligible']);

                if (!count($eligibleBranches)) return null;

                [$eligibleBranch] = $eligibleBranches->values();

                $mappedSize = $this->mapSize($modelNode, (string)$sizeXML->Sizedeno);
                $availability = $this->generateAvailabilityAttributeFromNonEligibleBranches($nonEligibleBranches);

                return [
                    'active' => true,
                    'number' => (string)$sizeXML->Itemno,
                    'ean' => (string)$sizeXML->Ean,
                    'lastStock' => true,
                    'prices' => [[
                        'price' => (float)$eligibleBranch->Saleprice,
                        'pseudoPrice' => $eligibleBranch->Xprice ? (float)$eligibleBranch->Xprice : null,
                    ]],
                    'inStock' => (int)$eligibleBranch->Stockqty,
                    'attribute' => [
                        'attr1' => (string)$sizeXML->Itemdeno,
                        'availability' => json_encode($availability),
                    ],
                    'configuratorOptions' => [
                        ['group' => 'Size', 'option' => $mappedSize],
                    ]
                ];
            })
            ->filter();

        return $variants;
    }

    protected function mapSize(SimpleXMLElement $modelNode, string $sourceSize): string
    {
        $manufacturer = (string)$modelNode->Branddeno;
        $fedas = (string)$modelNode->Fedas;

        return $this->sizeMapper->mapSize($manufacturer, $fedas, $sourceSize);
    }

    protected function generateAvailabilityAttributeFromNonEligibleBranches(Collection $nonEligibleBranches): Collection
    {
        return $nonEligibleBranches
            ->map(function (SimpleXMLElement $branchXML) {
                return [
                    'branchNo' => (string)$branchXML->Branchno,
                    'stock' => (int)$branchXML->Stockqty,
                ];
            })
            ->values();
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

    protected function isNewImportFileForArticle(\App\ImportFile $importFile, Article $article): bool
    {
        if ($article->imports()->where('import_file_id', $importFile->id)->exists()) return false;

        return $article->imports()
            ->whereHas('importFile', function ($q) use ($importFile) {
                $q->where('original_filename', '>=', $importFile->original_filename);
            })
            ->doesntExist();
    }


}
