<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SizeMappingExclusion
 * @package App
 *
 * @property int $id
 * @property string $article_number
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SizeMappingExclusion extends Model
{
    use HasFactory;

    protected $fillable = ['article_number'];
}
