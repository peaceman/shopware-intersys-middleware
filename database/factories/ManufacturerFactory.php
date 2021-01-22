<?php

namespace Database\Factories;

use App\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManufacturerFactory extends Factory
{
    protected $model = Manufacturer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
        ];
    }
}
