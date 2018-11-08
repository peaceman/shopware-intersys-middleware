<?php
/**
 * lel since 08.11.18
 */

namespace Tests\Unit\Domain\OrderTracking;


use App\Domain\OrderTracking\UnpaidOrderProvider;
use App\Order;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class UnpaidOrderProviderTest extends TestCase
{
    use DatabaseMigrations;

    public function testOrderProvider()
    {
        $unpaidOrder = factory(Order::class)->create([
            'sw_payment_status_id' => 4,
        ]);

        $paidOrder = factory(Order::class)->create([
            'sw_payment_status_id' => 8,
        ]);

        $orderProvider = new UnpaidOrderProvider();
        $orderProvider->setUnpaidPaymentStatusIDs([4]);

        $orders = iterator_to_array($orderProvider->getOrders());
        static::assertCount(1, $orders);

        static::assertEquals($unpaidOrder->id, array_shift($orders)->id);
    }
}
