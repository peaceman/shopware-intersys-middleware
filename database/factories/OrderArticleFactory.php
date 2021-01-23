<?php

namespace Database\Factories;

use App\OrderArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderArticleFactory extends Factory
{
    protected $model = OrderArticle::class;

    public function definition()
    {
        return [
            'sw_position_id' => $this->faker->unique()->randomNumber(2),
            'sw_article_number' => $this->faker->unique()->ean13,
            'sw_article_name' => $this->faker->sentence,
            'sw_quantity' => $this->faker->randomNumber(1),
        ];
    }
}

