<?php

use Faker\Generator as Faker;

$factory->define(App\Order::class, function (Faker $faker) {
    return [
        'sw_order_id' => $faker->unique()->randomNumber(),
        'sw_order_number' => $faker->unique()->randomNumber(),
        'sw_order_time' => $faker->date(),
        'sw_order_status_id' => $faker->randomNumber(1),
        'sw_payment_status_id' => $faker->randomNumber(1),
        'sw_payment_id' => $faker->randomNumber(1),
    ];
});
