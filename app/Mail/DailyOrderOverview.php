<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyOrderOverview extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var iterable
     */
    public $orders;

    /**
     * Create a new message instance.
     *
     * @param iterable $orders
     */
    public function __construct(iterable $orders)
    {
        $this->orders = $orders;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.orders.daily-overview');
    }
}
