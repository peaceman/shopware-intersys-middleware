<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class OrderXMLGenerator
{
    const TYPE_SALE = 'sale';
    const TYPE_RETURN = 'return';

    protected $accountingBranchNo;
    protected $stockBranchNo;

    /**
     * @var \DOMDocument
     */
    protected $xml;

    public function setAccountingBranchNo(string $accountingBranchNo): void
    {
        $this->accountingBranchNo = $accountingBranchNo;
    }

    public function setStockBranchNo(string $stockBranchNo): void
    {
        $this->stockBranchNo = $stockBranchNo;
    }

    public function generate(string $type, \DateTimeImmutable $exportDate, Order $order, array $orderArticles): string
    {
        try {
            $this->xml = new \DOMDocument('1.0', 'ISO-8859-1');
            $this->xml->formatOutput = true;
            $this->xml->appendChild($saleRoot = $this->createSaleRootElement($exportDate));

            foreach ($orderArticles as $orderArticle) {
                $itemElement = $this->createItemElement(
                    $type,
                    $orderArticle['dateOfTrans'],
                    $order,
                    $orderArticle['article']
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
        $sale->setAttribute('Branchno', $this->accountingBranchNo);

        return $sale;
    }

    protected function formatDate(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Ymd\TH:i:s');
    }

    protected function createItemElement(
        string $type,
        \DateTimeInterface $dateOfTrans,
        Order $order,
        OrderArticle $orderArticle
    ): \DOMElement
    {
        $item = $this->xml->createElement('Item');
        $item->appendChild($this->xml->createElement('Itemno', $orderArticle->getArticleNumber()));
        $item->appendChild($this->xml->createElement('Saleqty', $orderArticle->getQuantity()));
        $item->appendChild($this->createCostElement($orderArticle));
        $item->appendChild($this->xml->createElement('Dateoftrans', $this->formatDate($dateOfTrans)));
        $item->appendChild($this->xml->createElement('Type', $type === static::TYPE_RETURN ? 'R' : 'S'));
        $item->appendChild($this->xml->createElement('Branchno', $this->stockBranchNo));

        $commentEl = $this->xml->createElement('Comment');
        $commentEl->appendChild($this->xml->createCDATASection("OrderId: {$order->getOrderNumber()}"));
        $item->appendChild($commentEl);

        return $item;
    }

    /**
     * @param OrderArticle $orderArticle
     * @return \DOMElement
     */
    protected function createCostElement(OrderArticle $orderArticle): \DOMElement
    {
        return $this->xml->createElement(
            'Cost',
            number_format($orderArticle->getFullPrice(), 2, '.', '')
        );
    }
}
