<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ManufacturerSizeMapping
 * @package App
 *
 * @property int $id
 * @property int $manufacturer_id
 * @property string $gender
 * @property string $source_size
 * @property string $target_size
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 *
 * @property-read Manufacturer $manufacturer
 */
class ManufacturerSizeMapping extends Model
{
    use SoftDeletes;

    const GENDER_MALE_UNISEX = 'male';
    const GENDER_FEMALE = 'female';
    const GENDER_CHILD = 'child';

    protected $table = 'manufacturer_size_mappings';

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id', 'id');
    }
}
