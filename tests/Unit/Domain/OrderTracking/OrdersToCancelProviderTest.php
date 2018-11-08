<?php
/**
 * lel since 08.11.18
 */

namespace Tests\Unit\Domain\OrderTracking;

use App\Domain\OrderTracking\OrderProvider;
use App\Domain\OrderTracking\OrdersToCancelProvider;
use App\Order;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class OrdersToCancelProviderTest extends TestCase
{
    use DatabaseMigrations;

    private $unpaidPaymentStatus = 4;
    private $prePaymentId = 8;
    private $cancelWaitingTimeInDays = 5;

    public function testAlreadyCancelledOrder()
    {
        $order = factory(Order::class)->create([
            'sw_payment_status_id' => $this->unpaidPaymentStatus,
            'sw_order_time' => now()->subDays(8),
            'sw_payment_id' => $this->prePaymentId,
            'cancelled_at' => now(),
        ]);

        $orders = $this->createOrderProvider()->getOrders();
        static::assertEmpty($orders);
    }

    public function testWrongPaymentStatus()
    {
        $order = factory(Order::class)->create([
            'sw_payment_status_id' => 0,
            'sw_order_time' => now()->subDays(8),
            'sw_payment_id' => $this->prePaymentId,
        ]);

        $orders = $this->createOrderProvider()->getOrders();
        static::assertEmpty($orders);
    }

    public function testWrongPaymentID()
    {
        $order = factory(Order::class)->create([
            'sw_payment_status_id' => $this->unpaidPaymentStatus,
            'sw_order_time' => now()->subDays(8),
            'sw_payment_id' => 1,
        ]);

        $orders = $this->createOrderProvider()->getOrders();
        static::assertEmpty($orders);
    }

    public function testTooLateOrderTime()
    {
        $order = factory(Order::class)->create([
            'sw_payment_status_id' => $this->unpaidPaymentStatus,
            'sw_order_time' => now()->subDays(4),
            'sw_payment_id' => $this->prePaymentId,
        ]);

        $orders = $this->createOrderProvider()->getOrders();
        static::assertEmpty($orders);
    }

    public function testOrderToCancel()
    {
        $order = factory(Order::class)->create([
            'sw_payment_status_id' => $this->unpaidPaymentStatus,
            'sw_order_time' => now()->subDays(5),
            'sw_payment_id' => $this->prePaymentId,
        ]);

        $orders = $this->createOrderProvider()->getOrders();
        static::assertNotEmpty($orders);
        static::assertEquals($order->id, $orders[0]->id);
    }

    private function createOrderProvider(): OrderProvider
    {
        $op = new OrdersToCancelProvider();
        $op->setUnpaidPaymentStatusIDs([$this->unpaidPaymentStatus]);
        $op->setPrePaymentID($this->prePaymentId);
        $op->setCancelWaitingTimeInDays($this->cancelWaitingTimeInDays);

        return $op;
    }
}
