<?php
/**
 * lel since 01.11.18
 */

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class OrderExportArticle
 * @package App
 *
 * @property int $id
 * @property int $order_export_id
 * @property string $sw_article_number
 * @property Carbon $date_of_trans
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read OrderExport $orderExport
 */
class OrderExportArticle extends Model
{
    protected $table = 'order_export_articles';
    protected $dates = ['date_of_trans'];

    public function orderExport(): BelongsTo
    {
        return $this->belongsTo(OrderExport::class, 'order_export_id', 'id');
    }
}
