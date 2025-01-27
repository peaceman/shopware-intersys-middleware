<?php
/**
 * lel since 01.11.18
 */

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class OrderExport
 * @package App
 *
 * @property int $id
 * @property string $type
 * @property string $sw_order_number
 * @property int $sw_order_id
 * @property string $storage_path
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OrderExport extends Model
{
    protected $table = 'order_exports';
}
