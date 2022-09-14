<?php
/**
 * lel since 11.08.18
 */

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    const DEFAULTS_WEIGHT = '1KG';
    const DEFAULTS_SHIPPING_TIME = '1-3';

    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static $unguarded = true;

    public function imports(): HasMany
    {
        return $this->hasMany(ArticleImport::class, 'article_id', 'id');
    }

    public function numberEanMappings(): HasMany
    {
        return $this->hasMany(ArticleNumberEanMapping::class, 'article_id', 'id');
    }
}
