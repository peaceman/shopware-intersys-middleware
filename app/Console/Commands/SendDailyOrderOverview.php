<?php

namespace App\Console\Commands;

use App\Domain\OrderTracking\DailyOverviewOrderProvider;
use App\Mail\DailyOrderOverview;
use App\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Mail\Mailer;

class SendDailyOrderOverview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sw:send-daily-order-overview';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the daily order overview mail';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param Mailer $mailer
     * @param DailyOverviewOrderProvider $orderProvider
     * @return mixed
     */
    public function handle(Mailer $mailer, DailyOverviewOrderProvider $orderProvider)
    {
        $recipients = explode(',', config('shopware.order.dailyOverviewRecipients'));
        if (empty($recipients)) return;

        $orders = iterator_to_array($orderProvider->getOrders());

        $mailer->to($recipients)
            ->send(new DailyOrderOverview($orders));

        /** @var Order $order */
        foreach ($orders as $order) {
            $order->notified_at = now();
            $order->save();
        }
    }
}
