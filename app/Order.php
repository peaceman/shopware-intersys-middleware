<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Order
 * @package App
 *
 * @property int $id
 * @property int $sw_order_id
 * @property string $sw_order_number
 * @property Carbon $sw_order_time
 * @property int $sw_order_status_id
 * @property int $sw_payment_status_id
 * @property int $sw_payment_id
 * @property Carbon $notified_at
 * @property Carbon $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read OrderArticle[] $orderArticles
 */
class Order extends Model
{
    protected $table = 'orders';
    protected $dates = ['sw_order_time', 'notified_at', 'cancelled_at'];

    public function orderArticles(): HasMany
    {
        return $this->hasMany(OrderArticle::class, 'order_id', 'id');
    }

    public function cancel(): void
    {
        $this->cancelled_at = now();
        $this->save();
    }
}
