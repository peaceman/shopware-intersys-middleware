<?php
/**
 * lel since 20.08.18
 */

use Faker\Generator as Faker;

$factory->define(\App\Article::class, function (Faker $faker) {
    return [
        'is_modno' => $faker->ean13,
        'is_active' => true,
        'sw_article_id' => $faker->unique()->randomNumber(),
    ];
});