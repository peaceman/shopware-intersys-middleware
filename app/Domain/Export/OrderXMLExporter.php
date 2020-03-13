<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\ShopwareAPI;
use App\OrderExport;
use App\OrderExportArticle;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

class OrderXMLExporter
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Filesystem
     */
    private $localFS;

    /**
     * @var Filesystem
     */
    private $remoteFS;

    /**
     * @var string
     */
    private $baseFolder;

    /**
     * @var OrderXMLGenerator
     */
    private $orderXMLGenerator;

    /**
     * @var ShopwareAPI
     */
    private $shopwareAPI;

    /**
     * @var int
     */
    private $afterExportStatusSale;

    /**
     * @var int
     */
    private $afterExportStatusReturn;

    /**
     * @var int
     */
    private $afterExportPositionStatusReturn;

    /**
     * @var int
     */
    private $orderPositionStatusRequirementReturn;

    /**
     * OrderXMLExporter constructor.
     * @param LoggerInterface $logger
     * @param Filesystem $localFS
     * @param Filesystem $remoteFS
     * @param OrderXMLGenerator $orderXMLGenerator
     * @param ShopwareAPI $shopwareAPI
     */
    public function __construct(
        LoggerInterface $logger,
        Filesystem $localFS,
        Filesystem $remoteFS,
        OrderXMLGenerator $orderXMLGenerator,
        ShopwareAPI $shopwareAPI
    ) {
        $this->logger = $logger;
        $this->localFS = $localFS;
        $this->remoteFS = $remoteFS;
        $this->orderXMLGenerator = $orderXMLGenerator;
        $this->shopwareAPI = $shopwareAPI;
    }

    public function setBaseFolder(string $baseFolder)
    {
        $this->baseFolder = $baseFolder;
    }

    public function setAfterExportStatusSale(int $statusID)
    {
        $this->afterExportStatusSale = $statusID;
    }

    public function setAfterExportStatusReturn(int $statusID)
    {
        $this->afterExportStatusReturn = $statusID;
    }

    public function setAfterExportPositionStatusReturn(int $statusID)
    {
        $this->afterExportPositionStatusReturn = $statusID;
    }

    public function setOrderPositionStatusRequirementReturn(int $positionStatusID): void
    {
        $this->orderPositionStatusRequirementReturn = $positionStatusID;
    }

    public function export(string $type, OrderProvider $orderProvider)
    {
        $startTime = microtime(true);
        $this->logger->info(__METHOD__ . ' Starting order export', [
            'type' => $type
        ]);

        /** @var Order $order */
        foreach ($orderProvider->getOrders() as $order) {
            if (app()->runningUnitTests()) {
                $this->exportOrder($type, $order);
            } else {
                rescue(function () use ($type, $order) {
                    $this->exportOrder($type, $order);
                });
            }
        }

        $this->logger->info(__METHOD__ . ' Finished order export', [
            'type' => $type,
            'elapsed' => microtime(true) - $startTime,
        ]);
    }

    protected function exportOrder(string $type, Order $order): void
    {
        $loggingContext = ['orderNumber' => $order->getOrderNumber(), 'swOrderID' => $order->getID()];
        $this->logger->info(__METHOD__, $loggingContext);

        $articles = $order->getArticles();
        $articleInfo = $this->prepareArticlesForExport($type, $order, $articles);

        if (empty($articleInfo)) {
            $this->logger->info(__METHOD__ . ' Order has no articles to export', $loggingContext);
            return;
        }

        $exportXML = $this->orderXMLGenerator->generate($type, new DateTimeImmutable(), $order, $articleInfo);
        $this->storeExportXMLOnRemoteFS($type, $order, $exportXML);
        $orderExport = $this->createOrderExportEntries($type, $order, $articleInfo, $exportXML);

        $this->updateShopwareOrderState($type, $order);
        $this->logger->info(__METHOD__ . ' Finished', array_merge($loggingContext, ['orderExportID' => $orderExport->id]));
    }

    private function prepareArticlesForExport(string $type, Order $order, array $articles): array
    {
        [$voucherArticles, $nonVoucherArticles] = collect($articles)
            ->partition(function (OrderArticle $orderArticle) {
                return $orderArticle->isVoucher();
            });

        $nonVoucherFullPriceSum = $nonVoucherArticles
            ->map(function (OrderArticle $orderArticle) {
                return $orderArticle->getFullPrice();
            })
            ->sum();

        $voucherFullPriceSum = $voucherArticles
            ->map(function (OrderArticle $orderArticle) {
                return abs($orderArticle->getFullPrice());
            })
            ->sum();

        $voucherPercentage = $voucherFullPriceSum / $nonVoucherFullPriceSum;

        $articlesToExport = $nonVoucherArticles
            ->filter(function (OrderArticle $orderArticle) use ($type) {
                return $type === OrderExport::TYPE_SALE
                    ? true
                    : $this->hasRequiredPositionStatusForExport($orderArticle);
            })
            ->map(function (OrderArticle $orderArticle) use ($order, $voucherPercentage) {
                $orderArticle->setVoucherPercentage($voucherPercentage);

                return $this->prepareArticle($order, $orderArticle);
            })
            ->values()->toArray();

        return $articlesToExport;
    }

    private function hasRequiredPositionStatusForExport(OrderArticle $orderArticle): bool
    {
        return $orderArticle->getPositionStatusID() === $this->orderPositionStatusRequirementReturn;
    }

    private function prepareArticle(Order $order, OrderArticle $article): array
    {
        $dateOfTrans = $this->determineDateOfTransForArticle($order, $article);

        return [
            'dateOfTrans' => $dateOfTrans,
            'article' => $article,
        ];
    }

    private function determineDateOfTransForArticle(Order $order, OrderArticle $article): DateTimeImmutable
    {
        return $this->determineFreeDateOfTransForArticle($article, $order->getOrderTime());
    }

    private function determineFreeDateOfTransForArticle(
        OrderArticle $article,
        DateTimeImmutable $startTime
    ): DateTimeImmutable
    {
        $time = $startTime;

        while ($this->orderExportWithDateOfTransExists($article, $time)) {
            $time = $time->add(new DateInterval('PT1M'));
        }

        return $time;
    }

    /**
     * @param OrderArticle $article
     * @param DateTimeInterface $time
     * @return bool
     */
    private function orderExportWithDateOfTransExists(OrderArticle $article, DateTimeInterface $time): bool
    {
        $timeString = $time->format('Y-m-d H:i');

        return OrderExportArticle::query()
            ->whereRaw('date_of_trans - interval second(date_of_trans) second = cast(? as datetime)', $timeString)
            ->where('sw_article_number', $article->getArticleNumber())
            ->exists();
    }

    private function storeExportXMLOnRemoteFS(string $type, Order $order, string $exportXML): void
    {
        $remoteFilename = $this->generateRemoteFilenameForExportXML($type, $order);
        $this->remoteFS->put("{$this->baseFolder}/$remoteFilename", $exportXML);
    }

    private function generateRemoteFilenameForExportXML(string $type, Order $order)
    {
        $typePart = $type === OrderExport::TYPE_SALE ? 'S' : 'R';
        $orderTime = $order->getOrderTime()->format('Y-m-d_H-i-s');

        return "order-{$order->getOrderNumber()}{$typePart}_Webshop_{$orderTime}.xml";
    }

    private function createOrderExportEntries(
        string $type,
        Order $order,
        array $articleInfo,
        string $exportXML
    ): OrderExport
    {
        $orderExport = $this->createOrderExport($type, $order, $exportXML);

        foreach ($articleInfo as $ai) {
            $this->createOrderExportArticle($orderExport, $ai);
        }

        return $orderExport;
    }

    private function createOrderExport(string $type, Order $order, string $exportXML): OrderExport
    {
        $localFilename = str_random(40) . '.xml';
        $this->localFS->put($localFilename, $exportXML);

        $oe = new OrderExport();
        $oe->type = $type;
        $oe->sw_order_number = $order->getOrderNumber();
        $oe->sw_order_id = $order->getID();
        $oe->storage_path = $localFilename;

        $oe->save();

        return $oe;
    }

    private function createOrderExportArticle(OrderExport $orderExport, array $articleInfo): OrderExportArticle
    {
        /** @var OrderArticle $article */
        $article = $articleInfo['article'];

        $oea = new OrderExportArticle();
        $oea->orderExport()->associate($orderExport);
        $oea->sw_article_number = $article->getArticleNumber();
        $oea->date_of_trans = $articleInfo['dateOfTrans'];

        $oea->save();

        return $oea;
    }

    private function updateShopwareOrderState(string $type, Order $order)
    {
        if ($type === OrderExport::TYPE_SALE) {
            $newStatusID = $this->afterExportStatusSale;
            $details = [];
        } else {
            $newStatusID = $this->afterExportStatusReturn;

            $details = array_map(function (OrderArticle $orderArticle) {
                $data = ['id' => $orderArticle->getPositionID()];

                if ($this->hasRequiredPositionStatusForExport($orderArticle)) {
                    $data['status'] = $this->afterExportPositionStatusReturn;
                }

                return $data;
            }, $order->getArticles());
        }

        $this->shopwareAPI->updateOrderStatus(
            $order->getID(), $newStatusID, $details
        );
    }
}
