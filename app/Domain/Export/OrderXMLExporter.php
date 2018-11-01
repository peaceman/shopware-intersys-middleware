<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\ShopwareAPI;
use App\OrderExport;
use App\OrderExportArticle;
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
        $loggingContext = ['orderNumber' => $order, 'swOrderID' => $order->getID()];
        $this->logger->info(__METHOD__, $loggingContext);

        $articles = $order->getArticles();
        if (empty($articles)) {
            $this->logger->info(__METHOD__, ' Order has no articles', $loggingContext);
            return;
        }

        $articleInfo = $this->prepareArticles($order, $articles);

        $exportXML = $this->orderXMLGenerator->generate($type, new \DateTimeImmutable(), $order, $articleInfo);
        $this->storeExportXMLOnRemoteFS($type, $order, $exportXML);
        $orderExport = $this->createOrderExportEntries($type, $order, $articleInfo, $exportXML);

        $this->updateShopwareOrderState($type, $order);
        $this->logger->info(__METHOD__ . ' Finished', array_merge($loggingContext, ['orderExportID' => $orderExport->id]));
    }

    private function prepareArticles(Order $order, array $articles): array
    {
        return array_map(function (OrderArticle $article) use ($order) {
            return $this->prepareArticle($order, $article);
        }, $articles);
    }

    private function prepareArticle(Order $order, OrderArticle $article): array
    {
        $dateOfTrans = $this->determineFreeDateOfTransForArticle($article, $order->getOrderTime());

        return [
            'dateOfTrans' => $dateOfTrans,
            'article' => $article,
        ];
    }

    private function determineFreeDateOfTransForArticle(
        OrderArticle $article,
        \DateTimeImmutable $startTime
    ): \DateTimeImmutable
    {
        $time = $startTime;

        while ($this->orderExportWithDateOfTransExists($article, $time)) {
            $time = $time->add(new \DateInterval('P1M'));
        }

        return $time;
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
        $newStatusID = $type === OrderExport::TYPE_SALE
            ? $this->afterExportStatusSale
            : $this->afterExportStatusReturn;

        $this->shopwareAPI->updateOrderStatus($order->getID(), $newStatusID);
    }
}
