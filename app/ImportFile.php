<?php
/**
 * lel since 12.08.18
 */

namespace App;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 *
 * @property-read ArticleImport[] $articleImports
 */
class ImportFile extends Model
{
    const TYPE_BASE = 'base';
    const TYPE_DELTA = 'delta';

    protected static $unguarded = true;

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function qualifiesForImport(): bool
    {
        return $this->storage_path && !$this->processed_at;
    }

    public function scopeReadyForImport($query)
    {
        return $query->whereNotNull('storage_path')
            ->whereNull('processed_at');
    }

    public function articleImports(): HasMany
    {
        return $this->hasMany(ArticleImport::class, 'import_file_id', 'id');
    }

    public function asLoggingContext(): array
    {
        return $this->only(['id', 'type', 'original_filename', 'storage_path', 'processed_at']);
    }
}
