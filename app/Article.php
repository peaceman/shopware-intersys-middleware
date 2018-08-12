<?php
/**
 * lel since 11.08.18
 */

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Article
 * @package App
 *
 * @property int $id
 * @property string $is_modno
 * @property bool $is_active
 * @property int|null $sw_article_id
 *
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Article extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(ArticleImport::class, 'article_id', 'id');
    }
}