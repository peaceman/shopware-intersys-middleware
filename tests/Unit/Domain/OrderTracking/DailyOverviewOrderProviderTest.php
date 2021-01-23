<?php
/**
 * lel since 08.11.18
 */

namespace Tests\Unit\Domain\OrderTracking;


use App\Domain\OrderTracking\DailyOverviewOrderProvider;
use App\Domain\OrderTracking\OrderProvider;
use App\Order;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class DailyOverviewOrderProviderTest extends TestCase
{
    use DatabaseMigrations;

    public function testAlreadyNotifiedOrder()
    {
        $order = Order::factory()->create([
            'notified_at' => now(),
        ]);

        $orders = $this->createOrderProvider()->getOrders();
        static::assertEmpty($orders);
    }

    public function testOrderToNotifyAbout()
    {
        $order = Order::factory()->create();

        $orders = $this->createOrderProvider()->getOrders();
        static::assertNotEmpty($orders);
        static::assertEquals($order->id, $orders[0]->id);
    }

    private function createOrderProvider(): OrderProvider
    {
        $op = new DailyOverviewOrderProvider();

        return $op;
    }
}
