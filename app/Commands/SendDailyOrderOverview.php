<?php
/**
 * lel since 13.03.20
 */

namespace App\Commands;

use App\Domain\OrderTracking\DailyOverviewOrderProvider;
use App\Mail\DailyOrderOverview;
use App\Order;
use Illuminate\Contracts\Mail\Mailer;

class SendDailyOrderOverview
{
    /** @var Mailer */
    private $mailer;

    /** @var DailyOverviewOrderProvider */
    private $orderProvider;

    public function __construct(Mailer $mailer, DailyOverviewOrderProvider $orderProvider)
    {
        $this->mailer = $mailer;
        $this->orderProvider = $orderProvider;
    }

    public function __invoke(): void
    {
        $recipients = explode(',', config('shopware.order.dailyOverviewRecipients'));
        if (empty($recipients)) return;

        $orders = iterator_to_array($this->orderProvider->getOrders());

        $this->mailer->to($recipients)
            ->send(new DailyOrderOverview($orders));

        /** @var Order $order */
        foreach ($orders as $order) {
            $order->notified_at = now();
            $order->save();
        }
    }
}
