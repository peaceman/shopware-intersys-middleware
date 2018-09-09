<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Manufacturer
 * @package App
 *
 * @property int $id
 * @property string $name
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 *
 * @property-read ManufacturerSizeMapping[] $sizeMappings
 */
class Manufacturer extends Model
{
    use SoftDeletes;

    protected $table = 'manufacturers';

    public function sizeMappings(): HasMany
    {
        return $this->hasMany(ManufacturerSizeMapping::class, 'manufacturer_id', 'id');
    }
}
