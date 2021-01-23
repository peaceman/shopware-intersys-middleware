<?php
/**
 * lel since 20.08.18
 */

namespace Database\Factories;

use App\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition()
    {
        return [
            'is_modno' => $this->faker->ean13,
            'is_active' => true,
            'sw_article_id' => $this->faker->unique()->randomNumber(),
        ];
    }
}
