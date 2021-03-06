<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class OrderArticle
 * @package App
 *
 * @property int $id
 * @property int $order_id
 * @property int $sw_position_id
 * @property string $sw_article_number
 * @property string $sw_article_name
 * @property int $sw_quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Order $order
 */
class OrderArticle extends Model
{
    use HasFactory;

    protected $table = 'order_articles';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
