<?php
/**
 * lel since 12.08.18
 */

namespace App;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ImportFile
 * @package App
 *
 * @property int $id
 * @property string $type
 * @property string $original_filename
 * @property string|null $storage_path
 *
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ImportFile extends Model
{
    protected static $unguarded = true;

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function qualifiesForImport(): bool
    {
        return $this->storage_path && !$this->processed_at;
    }
}