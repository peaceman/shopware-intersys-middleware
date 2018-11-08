<?php
/**
 * lel since 08.11.18
 */
namespace Tests\Unit\Listeners;

use App\Domain\Export\Order as ExportOrder;
use App\Domain\Export\OrderArticle as ExportOrderArticle;
use App\Domain\Export\OrderFetched;
use App\Order;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PersistOrderTest extends TestCase
{
    use DatabaseMigrations;

    public function testNewOrderIsPersisted()
    {
        $exportOrder = $this->generateExportOrder();

        event(new OrderFetched($exportOrder));

        /** @var Order $order */
        $order = Order::query()->where('sw_order_number', $exportOrder->getOrderNumber())->first();

        static::assertNotNull($order);
        $this->compareOrders($order, $exportOrder);

        $exportOrderArticles = $exportOrder->getArticles();
        $orderArticles = $order->orderArticles->all();
        static::assertEquals(count($exportOrderArticles), count($orderArticles));

        $exportOrderArticle = array_shift($exportOrderArticles);
        $orderArticle = array_shift($orderArticles);

        static::assertNotNull($orderArticle);
        static::assertEquals($exportOrderArticle->getArticleNumber(), $orderArticle->sw_article_number);
        static::assertEquals($exportOrderArticle->getArticleName(), $orderArticle->sw_article_name);
        static::assertEquals($exportOrderArticle->getQuantity(), $orderArticle->sw_quantity);
        static::assertEquals($exportOrderArticle->getPositionID(), $orderArticle->sw_position_id);

        $exportOrderArticle = array_shift($exportOrderArticles);
        $orderArticle = array_shift($orderArticles);

        static::assertNotNull($orderArticle);
        static::assertEquals($exportOrderArticle->getArticleNumber(), $orderArticle->sw_article_number);
        static::assertEquals($exportOrderArticle->getArticleName(), $orderArticle->sw_article_name);
        static::assertEquals($exportOrderArticle->getQuantity(), $orderArticle->sw_quantity);
        static::assertEquals($exportOrderArticle->getPositionID(), $orderArticle->sw_position_id);
    }

    public function testExistingOrderIsUpdated()
    {
        $orderTime = new \DateTimeImmutable();

        $exportOrder = new ExportOrder([
            'id' => 5,
            'number' => '23235',
            'orderTime' => $orderTime->format(\DateTime::ISO8601),
            'orderStatus' => [
                'id' => 4 + 23,
            ],
            'paymentStatus' => [
                'id' => 8 + 23,
            ],
            'paymentId' => 15,
        ]);

        $order = new Order();
        $order->sw_order_id = 5;
        $order->sw_order_number = '23235';
        $order->sw_order_time = $orderTime;
        $order->sw_order_status_id = 4;
        $order->sw_payment_status_id = 8;
        $order->sw_payment_id = 15;
        $order->save();

        event(new OrderFetched($exportOrder));

        $this->compareOrders($order->refresh(), $exportOrder);
    }

    protected function generateExportOrder(): ExportOrder
    {
        $order = new ExportOrder([
            'id' => 5,
            'number' => '23235',
            'orderTime' => \DateTimeImmutable::createFromFormat('Ymd-His', '20181031-230555')
                ->format(\DateTime::ISO8601),
            'orderStatus' => [
                'id' => 4,
            ],
            'paymentStatus' => [
                'id' => 8,
            ],
            'paymentId' => 15,
        ]);

        $order->setArticles([
            new ExportOrderArticle([
                'id' => 11,
                'articleNumber' => 'ABC123',
                'articleName' => 'first article',
                'price' => 23.5,
                'quantity' => 23,
                'statusId' => 15,
                'mode' => 0,
            ]),
            new ExportOrderArticle([
                'id' => 12,
                'articleNumber' => 'ABC123',
                'articleName' => 'second article',
                'price' => 23.5,
                'quantity' => 23,
                'statusId' => 45,
                'mode' => 0,
            ]),
        ]);

        return $order;
    }

    /**
     * @param $order
     * @param $exportOrder
     */
    private function compareOrders(Order $order, ExportOrder $exportOrder): void
    {
        static::assertEquals($exportOrder->getID(), $order->sw_order_id);
        static::assertTrue($this->compareDateTime($exportOrder->getOrderTime(), $order->sw_order_time));
        static::assertEquals($exportOrder->getOrderStatusID(), $order->sw_order_status_id);
        static::assertEquals($exportOrder->getPaymentStatusID(), $order->sw_payment_status_id);
        static::assertEquals($exportOrder->getPaymentID(), $order->sw_payment_id);
        static::assertNull($order->notified_at);
        static::assertNull($order->cancelled_at);
        static::assertNotNull($order->created_at);
    }
}
