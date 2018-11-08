<?php

use Faker\Generator as Faker;

$factory->define(App\OrderArticle::class, function (Faker $faker) {
    return [
        'sw_position_id' => $faker->unique()->randomNumber(2),
        'sw_article_number' => $faker->unique()->ean13,
        'sw_article_name' => $faker->sentence,
        'sw_quantity' => $faker->randomNumber(1),
    ];
});
