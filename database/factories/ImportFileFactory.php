<?php
/** @var Factory $factory */

use App\ImportFile;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(ImportFile::class, function (Faker $faker) {
    return [
        'type' => $faker->randomElement([ImportFile::TYPE_BASE, ImportFile::TYPE_DELTA]),
        'original_filename' => $faker->slug . '.' . $faker->fileExtension,
        'storage_path' => $faker->md5,
    ];
});
