<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\ShopwareAPI;
use App\OrderExport;
use App\OrderExportArticle;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
        /** @var Order $order */
        foreach ($orderProvider->getOrders() as $order) {
            rescue(function () use ($type, $order) {
                $this->exportOrder($type, $order);
            });
        }
    }

    protected function exportOrder(string $type, Order $order): void
    {
        $loggingContext = ['orderNumber' => $order->getOrderNumber(), 'swOrderID' => $order->getID()];
        $this->logger->info(__METHOD__, $loggingContext);

        $articles = $order->getArticles();
        $articleInfo = $this->prepareArticles($type, $order, $articles);

        if (empty($articleInfo)) {
            $this->logger->info(__METHOD__, ' Order has no articles to export', $loggingContext);
            return;
        }

        $exportXML = $this->orderXMLGenerator->generate($type, new \DateTimeImmutable(), $order, $articleInfo);
        $this->storeExportXMLOnRemoteFS($type, $order, $exportXML);
        $orderExport = $this->createOrderExportEntries($type, $order, $articleInfo, $exportXML);

        $this->updateShopwareOrderState($type, $order);
        $this->logger->info(__METHOD__ . ' Finished', array_merge($loggingContext, ['orderExportID' => $orderExport->id]));
    }

    private function prepareArticles(string $type, Order $order, array $articles): array
    {
        return collect($articles)
            ->filter(function (OrderArticle $orderArticle) use ($type) {
                return $type === OrderExport::TYPE_SALE
                    ? true
                    : $this->hasRequiredPositionStatusForExport($orderArticle);
            })
            ->map(function (OrderArticle $orderArticle) use ($type, $order) {
                return $this->prepareArticle($type, $order, $orderArticle);
            })
            ->values()->toArray();
    }

    private function hasRequiredPositionStatusForExport(OrderArticle $orderArticle): bool
    {
        return $orderArticle->getPositionStatusID() === $this->orderPositionStatusRequirementReturn;
    }

    private function prepareArticle(string $type, Order $order, OrderArticle $article): array
    {
        $dateOfTrans = $this->determineDateOfTransForArticle($type, $order, $article);

        return [
            'dateOfTrans' => $dateOfTrans,
            'article' => $article,
        ];
    }

    private function determineDateOfTransForArticle(string $type, Order $order, OrderArticle $article): \DateTimeImmutable
    {
        return $type === OrderExport::TYPE_SALE
            ? $this->determineFreeDateOfTransForArticle($article, $order->getOrderTime())
            : $this->determineDateOfTransForReturnArticle($order, $article);
    }

    private function determineFreeDateOfTransForArticle(
        OrderArticle $article,
        \DateTimeImmutable $startTime
    ): \DateTimeImmutable
    {
        $time = $startTime;

        while ($this->orderExportWithDateOfTransExists($article, $time)) {
            $time = $time->add(new \DateInterval('PT1M'));
        }

        return $time;
    }

    private function determineDateOfTransForReturnArticle(Order $order, OrderArticle $article): \DateTimeImmutable
    {
        /** @var OrderExportArticle $saleOrderArticle */
        $saleOrderArticle = OrderExportArticle::query()
            ->whereHas('orderExport', function (Builder $q) use ($order) {
                $q->where('sw_order_number', $order->getOrderNumber());
            })
            ->where('sw_article_number', $article->getArticleNumber())
            ->first();

        if (!$saleOrderArticle) {
            $this->logger->warning(__METHOD__ . ' Failed to find the corresponding sale order for a return order', [
                'orderNumber' => $order->getOrderNumber(),
                'orderTime' => $order->getOrderTime(),
                'swArticleNumber' => $article->getArticleNumber(),
            ]);

            throw new RuntimeException('Failed to find the corresponding sale order for a return order');
        }

        return \DateTimeImmutable::createFromMutable($saleOrderArticle->date_of_trans);
    }

    /**
     * @param OrderArticle $article
     * @param \DateTimeInterface $time
     * @return bool
     */
    private function orderExportWithDateOfTransExists(OrderArticle $article, \DateTimeInterface $time): bool
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
