<?php
/**
 * lel since 11.08.18
 */

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ArticleImport
 * @package App
 *
 * @property int $id
 * @property int $article_id
 * @property int $import_file_id
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ArticleImport extends Model
{
    protected static $unguarded = true;

    public function importFile(): BelongsTo
    {
        return $this->belongsTo(ImportFile::class, 'import_file_id', 'id');
    }
}