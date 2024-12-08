<?php
/**
 * lel since 20.08.18
 */

namespace Database\Factories;

use App\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition()
    {
        return [
            'is_modno' => $this->faker->ean13,
            'is_active' => true,
            'sw_product_id' => (string) Str::uuid()->getHex(),
        ];
    }
}
