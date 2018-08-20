<?php
/**
 * lel since 11.08.18
 */
namespace App\Domain\Import;

use App\Article;
use App\Domain\ShopwareAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
     * @var string[]
     */
    protected $branchesToImport = [];

    /**
     * ModelXMLImporter constructor.
     * @param LoggerInterface $logger
     * @param ShopwareAPI $shopwareAPI
     */
    public function __construct(LoggerInterface $logger, ShopwareAPI $shopwareAPI)
    {
        $this->logger = $logger;
        $this->shopwareAPI = $shopwareAPI;
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
        if (!$this->isEligibleForImport($modelXML)) {
            $this->logger->info(__METHOD__ . ' Model is not eligible for import', [
                'sourceFilename' => $modelXMLData->getImportFile()->original_filename,
                'model.code' => (string)$modelXML->Code,
            ]);

            return;
        }

        $article = $this->tryToFetchShopwareArticle($modelXML);

        if ($article && !$this->isNewImportFileForArticle($modelXMLData->getImportFile(), $article)) {
            $this->logger->info(__METHOD__ . ' Already imported the same or newer data for the article', [
                'sourceFilename' => $modelXMLData->getImportFile()->original_filename,
                'model.code' => (string)$modelXML->Code,
                'article.id' => $article->id,
            ]);
        } else {
            $article = $article
                ? $this->updateArticle($article, $modelXMLData)
                : $this->createArticle($modelXMLData);
        }

        $article->imports()->create(['import_file_id' => $modelXMLData->getImportFile()->id]);
    }

    protected function isEligibleForImport(SimpleXMLElement $modelXML): bool
    {
        $branches = $modelXML->xpath('/Model/Color/Size/Branch');

        return collect($branches)
            ->first([$this, 'isBranchEligible']) !== null;
    }

    public function isBranchEligible(SimpleXMLElement $branchXML): bool
    {
        return in_array($branchXML->Branchno ?? null, $this->branchesToImport);
    }

    protected function tryToFetchShopwareArticle(SimpleXMLElement $modelXML): ?Article
    {
        $articleNumber = (string)$modelXML->Modno;
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

    protected function updateArticle(Article $article, ModelXMLData $modelXMLData)
    {
        $modelXML = $modelXMLData->getSimpleXMLElement();
        $swArticleId = $article->sw_article_id;

        $loggingContext = [
            'articleNumber' => (string)$modelXML->Modno,
            'swArticleId' => $swArticleId,
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariantsFromModelXML($modelXML);
        $pricesOfTheFirstVariant = data_get($variants, '0.prices');

        $articleData = [
            'active' => true,
            'name' => (string)$modelXML->Moddeno,
            'tax' => (string)$modelXML->Percentvat,
            'supplier' => (string)$modelXML->Branddeno,
            'descriptionLong' => (string)$modelXML->Longdescription,
            'mainDetail' => [
                'number' => (string)$modelXML->Modno,
                'prices' => $pricesOfTheFirstVariant,
            ],
            'configuratorSet' => [
                'groups' => $this->createConfiguratorSetGroupsFromVariants($variants),
            ],
            'variants' => $variants,
        ];

        $this->shopwareAPI->updateShopwareArticle($swArticleId, $articleData);

        $this->logger->info(__METHOD__ . ' Updated Article', $loggingContext);

        return $article;
    }

    protected function createArticle(ModelXMLData $modelXMLData): Article
    {
        $modelXML = $modelXMLData->getSimpleXMLElement();
        $loggingContext = [
            'articleNumber' => (string)$modelXML->Moddeno,
        ];

        $this->logger->info(__METHOD__, $loggingContext);

        $variants = $this->generateVariantsFromModelXML($modelXML);
        $pricesOfTheFirstVariant = data_get($variants, '0.prices');

        $articleNumber = (string)$modelXML->Modno;
        $articleData = [
            'active' => true,
            'name' => (string)$modelXML->Moddeno,
            'tax' => (string)$modelXML->Percentvat,
            'supplier' => (string)$modelXML->Branddeno,
            'descriptionLong' => (string)$modelXML->Longdescription,
            'mainDetail' => [
                'number' => (string)$modelXML->Modno,
                'prices' => $pricesOfTheFirstVariant,
            ],
            'configuratorSet' => [
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

    /**
     * @param SimpleXMLElement $modelXML
     * @return Collection
     */
    protected function generateVariantsFromModelXML(SimpleXMLElement $modelXML): Collection
    {
        $variants = collect($modelXML->xpath('Color'))
            ->flatMap(function (SimpleXMLElement $colorXML) {
                return collect($colorXML->xpath('Size'))
                    ->flatMap(function (SimpleXMLElement $sizeXML) use ($colorXML) {
                        $eligibleBranches = collect($sizeXML->xpath('Branch'))
                            ->filter([$this, 'isBranchEligible']);

                        return $eligibleBranches
                            ->map(function (SimpleXMLElement $branchXML) use ($sizeXML, $colorXML) {
                                return [
                                    'additionaltext' => (string)$colorXML->Colordeno,
                                    'number' => (string)$sizeXML->Itemno,
                                    'ean' => (string)$sizeXML->Ean,
                                    'prices' => [[
                                        'price' => (float)$branchXML->Saleprice,
                                        'pseudoPrice' => $branchXML->Xprice ? (float)$branchXML->Xprice : null,
                                    ]],
                                    'inStock' => (int)$branchXML->Stockqty,
                                    'attribute' => [
                                        'attr1' => (string)$sizeXML->Itemdeno,
                                    ],
                                    'configuratorOptions' => [
                                        ['group' => 'Color', 'option' => (string)$colorXML->Colordeno],
                                        ['group' => 'Size', 'option' => (string)$sizeXML->Sizedeno],
                                    ]
                                ];
                            })
                            ->values();
                    })
                    ->values();
            });

        return $variants;
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