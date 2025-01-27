<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

use Illuminate\Support\Str;

class OrderXMLGenerator
{
    protected $stockBranchNo;

    /**
     * @var \DOMDocument
     */
    protected $xml;


    public function setStockBranchNo(string $stockBranchNo): void
    {
        $this->stockBranchNo = $stockBranchNo;
    }

    /**
     * @param OrderExportType $type
     * @param \DateTimeImmutable $exportDate
     * @param OrderDTO $order
     * @param OrderLineItemDTO[] $lineItems
     * @return string
     */
    public function generate(
        OrderExportType $type,
        \DateTimeImmutable $exportDate,
        OrderDTO $order,
        array $lineItems
    ): string {
        try {
            $this->xml = new \DOMDocument('1.0', 'ISO-8859-1');
            $this->xml->formatOutput = true;
            $this->xml->appendChild($saleRoot = $this->createSaleRootElement($exportDate));

            foreach ($lineItems as $lineItem) {
                $itemElement = $this->createItemElement(
                    $type,
                    $order,
                    $lineItem
                );

                $saleRoot->appendChild($itemElement);
            }

            return $this->xml->saveXML();
        } finally {
            $this->xml = null;
        }
    }

    protected function createSaleRootElement(\DateTimeImmutable $exportDate): \DOMElement
    {
        $sale = $this->xml->createElement('Sale');
        $sale->setAttribute('Exportdate', $this->formatDate($exportDate));
        $sale->setAttribute('Exporttype', 'Sale');
        $sale->setAttribute('Branchno', 'WEB');

        return $sale;
    }

    protected function formatDate(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Ymd\TH:i:s');
    }

    protected function createItemElement(
        OrderExportType $type,
        OrderDTO $order,
        OrderLineItemDTO $lineItem
    ): \DOMElement {
        $item = $this->xml->createElement('Item');
        $item->appendChild($this->xml->createElement('Itemno', $lineItem->getEan()));
        $item->appendChild($this->xml->createElement('Saleqty', $lineItem->getQuantity()));
        $item->appendChild($this->createCostElement($lineItem));
        $item->appendChild($this->xml->createElement('Dateoftrans', $this->formatDate($order->getOrderTime())));
        $item->appendChild($this->xml->createElement('Type', match ($type) {
            OrderExportType::Sale => 'S',
            OrderExportType::Return => 'R',
        }));
        $item->appendChild($this->createRefnoElement($type, $order));
        $item->appendChild($this->xml->createElement('Branchno', $this->stockBranchNo));

        $commentEl = $this->xml->createElement('Comment');
        $commentEl->appendChild($this->xml->createCDATASection("OrderNumber: {$order->getOrderNumber()}, ProductNumber: {$lineItem->getProductNumber()}"));
        $item->appendChild($commentEl);

        return $item;
    }

    protected function createCostElement(OrderLineItemDTO $lineItem): \DOMElement
    {
        return $this->xml->createElement(
            'Cost',
            number_format($lineItem->getPrice(), 2, '.', '')
        );
    }

    protected function createRefnoElement(OrderExportType $type, OrderDTO $order): \DOMElement
    {
        $refNo = implode('-', [
            match ($type) {
                OrderExportType::Return => 'GS',
                OrderExportType::Sale => 'RE',
            },
            Str::padLeft($order->getOrderNumber(), 8, '0'),
        ]);

        $refNoEl = $this->xml->createElement('Refno');
        $refNoEl->append($this->xml->createCDATASection($refNo));

        return $refNoEl;
    }
}
