<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $article_id
 * @property string $ean
 * @property string $article_number
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ArticleNumberEanMapping extends Model
{
    protected static $unguarded = true;

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id', 'id');
    }
}
