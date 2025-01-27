<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use App\Domain\Shopware6API;
use App\OrderExport;
use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class OrderXMLExporter
{
    private LoggerInterface $logger;
    private Filesystem $localFS;
    private Filesystem $remoteFS;
    private OrderXMLGenerator $orderXMLGenerator;
    private Shopware6API $shopwareAPI;

    private ?string $baseFolder = null;
    private ?string $orderNumberPrefix = null;

    public function __construct(
        LoggerInterface $logger,
        Filesystem $localFS,
        Filesystem $remoteFS,
        OrderXMLGenerator $orderXMLGenerator,
        Shopware6API $shopwareAPI
    ) {
        $this->logger = $logger;
        $this->localFS = $localFS;
        $this->remoteFS = $remoteFS;
        $this->orderXMLGenerator = $orderXMLGenerator;
        $this->shopwareAPI = $shopwareAPI;
    }

    public function setBaseFolder(string $baseFolder): void
    {
        $this->baseFolder = $baseFolder;
    }

    public function setOrderNumberPrefix(?string $orderNumberPrefix): void
    {
        $this->orderNumberPrefix = $orderNumberPrefix;
    }

    public function export(OrderExportType $type, OrderProvider $orderProvider): void
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

    protected function exportOrder(OrderExportType $type, OrderDTO $order): void
    {
        $loggingContext = ['orderNumber' => $order->getOrderNumber(), 'swOrderID' => $order->getId()];
        $this->logger->info(__METHOD__, $loggingContext);

        $lineItems = $order->getLineItems();
        $exportableLineItems = $this->filterLineItems($lineItems);

        if (empty($exportableLineItems)) {
            $this->logger->info(__METHOD__ . ' Order has no articles to export', $loggingContext);
            return;
        }

        $exportXML = $this->orderXMLGenerator->generate($type, new DateTimeImmutable(), $order, $exportableLineItems);
        $this->storeExportXMLOnRemoteFS($type, $order, $exportXML);
        $orderExport = $this->createOrderExport($type, $order, $exportXML);

        $this->updateShopwareOrderState($type, $order);
        $this->logger->info(__METHOD__ . ' Finished', array_merge($loggingContext, ['orderExportID' => $orderExport->id]));
    }

    protected function filterLineItems(array $lineItems): array
    {
        return array_values(array_filter(
            $lineItems,
            fn (OrderLineItemDTO $oli): bool => $oli->isProduct() && !empty($oli->getEan())
        ));
    }

    private function storeExportXMLOnRemoteFS(OrderExportType $type, OrderDTO $order, string $exportXML): void
    {
        $remoteFilename = $this->generateRemoteFilenameForExportXML($type, $order);
        $this->remoteFS->put("{$this->baseFolder}/$remoteFilename", $exportXML);
    }

    private function generateRemoteFilenameForExportXML(OrderExportType $type, OrderDTO $order): string
    {
        $typePart = match($type) {
            OrderExportType::Return => 'R',
            OrderExportType::Sale => 'S',
        };

        $orderTime = $order->getOrderTime()->format('Y-m-d_H-i-s');

        return "order-{$this->getOrderNumberString($order)}{$typePart}_Webshop_{$orderTime}.xml";
    }

    private function getOrderNumberString(OrderDTO $order): string
    {
        $orderNumberParts = array_filter(
            [$this->orderNumberPrefix, $order->getOrderNumber()],
            static function (?string $v) {
                return !empty($v);
            }
        );

        return implode('-', $orderNumberParts);
    }

    private function createOrderExport(OrderExportType $type, OrderDTO $order, string $exportXML): OrderExport
    {
        $localFilename = Str::random(40) . '.xml';
        $this->localFS->put($localFilename, $exportXML);

        $oe = new OrderExport();
        $oe->type = $type;
        $oe->sw_order_number = $order->getOrderNumber();
        $oe->sw_order_id = $order->getId();
        $oe->storage_path = $localFilename;

        $oe->save();

        return $oe;
    }

    private function updateShopwareOrderState(OrderExportType $type, OrderDTO $order): void
    {
        match ($type) {
            OrderExportType::Sale => $this->setShopwareOrderInProcess($order),
            OrderExportType::Return => $this->flagShopwareReturnAsTransferred($order),
        };
    }

    private function setShopwareOrderInProcess(OrderDTO $order): void
    {
        $this->shopwareAPI->updateOrderState($order->getId(), 'process');
    }

    private function flagShopwareReturnAsTransferred(OrderDTO $order): void
    {
        // todo implement
    }
}
