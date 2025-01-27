<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderArticle;
use App\Domain\Export\OrderExportType;
use App\Domain\Export\OrderXMLGenerator;
use App\OrderExport;
use Tests\TestCase;

class OrderXMLGeneratorTest extends TestCase
{
    public function testSaleXMLGeneration()
    {
        $orderXMLGenerator = new OrderXMLGenerator();
        $orderXMLGenerator->setStockBranchNo('005');

        $order = new Order([
            'orderNumber' => '23235',
            'orderDateTime' => '2018-10-31T20:12:42+0100',
            'lineItems' => [
                [
                    'quantity' => 23,
                    'product' => ['productNumber' => 'ABC123', 'ean' => 'is dis ean'],
                    'price' => ['totalPrice' => 23 * 23.5],
                ],
                [
                    'quantity' => 23,
                    'product' => ['productNumber' => 'ABC127', 'ean' => 'dis is ean'],
                    'price' => ['totalPrice' => 23 * 23.5],
                ]
            ],
        ]);

        $testDate = \DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555');
        $exportDate = $testDate;

        $saleXMLContent = $orderXMLGenerator->generate(OrderExportType::Sale, $exportDate, $order, $order->getLineItems());
        static::assertEquals(
            file_get_contents(base_path('docs/fixtures/export-sale.xml')),
            $saleXMLContent
        );
    }
}
