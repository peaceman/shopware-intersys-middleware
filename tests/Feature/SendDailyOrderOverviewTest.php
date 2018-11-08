<?php
/**
 * lel since 08.11.18
 */

namespace Tests\Feature;


use App\Console\Commands\SendDailyOrderOverview;
use App\Mail\DailyOrderOverview;
use App\Order;
use App\OrderArticle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDailyOrderOverviewTest extends TestCase
{
    use DatabaseMigrations;

    public function testMail()
    {
        Mail::fake();

        config(['shopware.order.dailyOverviewRecipients' => 'test@example.com']);

        $orders = factory(Order::class, 3)->create();
        collect($orders)->each(function (Order $order) {
            $orderArticles = factory(OrderArticle::class, 3)->make();
            $order->orderArticles()->saveMany($orderArticles);
        });

        Artisan::call(SendDailyOrderOverview::class);

        Mail::assertSent(DailyOrderOverview::class);
    }
}
