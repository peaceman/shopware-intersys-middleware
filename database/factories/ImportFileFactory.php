<?php

namespace Database\Factories;

use App\ImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportFileFactory extends Factory
{
    protected $model = ImportFile::class;

    public function definition()
    {
        return [
            'type' => $this->faker->randomElement([ImportFile::TYPE_BASE, ImportFile::TYPE_DELTA]),
            'original_filename' => $this->faker->slug . '.' . $this->faker->fileExtension,
            'storage_path' => $this->faker->md5,
        ];
    }
}
