<?php
/**
 * lel since 01.11.18
 */

namespace Tests\Unit\Domain\Export;

use App\Domain\Export\Order;
use App\Domain\Export\OrderArticle;
use App\Domain\Export\OrderXMLGenerator;
use App\OrderExport;
use Tests\TestCase;

class OrderXMLGeneratorTest extends TestCase
{
    public function testSaleXMLGeneration()
    {
        $orderXMLGenerator = new OrderXMLGenerator();
        $orderXMLGenerator->setAccountingBranchNo('004');
        $orderXMLGenerator->setStockBranchNo('005');

        $order = new Order([
            'number' => '23235',
            'orderTime' => '2018-10-31T20:12:42+0100',
        ]);

        $testDate = \DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555');
        $orderArticles = [
            [
                'dateOfTrans' => $testDate,
                'article' => new OrderArticle([
                    'articleNumber' => 'ABC123',
                    'price' => 23.5,
                    'quantity' => 23,
                ]),
            ],
            [
                'dateOfTrans' => $testDate,
                'article' => (new OrderArticle([
                    'articleNumber' => 'ABC127',
                    'price' => 23.5,
                    'quantity' => 23,
                ]))->setVoucherPercentage(0.1),
            ],
        ];

        $exportDate = $testDate;

        $saleXMLContent = $orderXMLGenerator->generate(OrderExport::TYPE_SALE, $exportDate, $order, $orderArticles);
        static::assertEquals(
            file_get_contents(base_path('docs/fixtures/export-sale.xml')),
            $saleXMLContent
        );
    }
}
