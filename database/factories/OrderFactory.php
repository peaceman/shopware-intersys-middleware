<?php

namespace Database\Factories;

use App\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'sw_order_id' => $this->faker->unique()->randomNumber(),
            'sw_order_number' => $this->faker->unique()->randomNumber(),
            'sw_order_time' => $this->faker->date(),
            'sw_order_status_id' => $this->faker->randomNumber(1),
            'sw_payment_status_id' => $this->faker->randomNumber(1),
            'sw_payment_id' => $this->faker->randomNumber(1),
        ];
    }
}
