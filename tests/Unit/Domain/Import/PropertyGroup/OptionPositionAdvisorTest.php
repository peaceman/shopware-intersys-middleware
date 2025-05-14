<?php

namespace Tests\Unit\Domain\Import\PropertyGroup;

use App\Domain\Import\PropertyGroup\OptionPositionAdvisorSize;
use PHPUnit\Framework\TestCase;

class OptionPositionAdvisorTest extends TestCase
{
    public static function adviseProvider(): array
    {
        // aaa-bb-cc-ddd
        // a -> regular number
        // b -> decimals
        // c -> letter suffixes
        // d -> fractions

        return [
            'regular number' => ['45', 45_00_00_000],
            'dot decimal' => ['23.5', 23_50_00_000],
            'comma decimal' => ['23,5', 23_50_00_000],
            'random words' => ['one size', 2**32],
            'suffixed number' => ['95D', 95_00_04_000],
            'partial numbers' => ['7 3/8', 7_00_00_375],
            'XXS' => ['XXS', 1],
            'XS' => ['XS', 2],
            'S' => ['S', 3],
            'M' => ['M', 4],
            'L' => ['L', 5],
            'XL' => ['XL', 6],
            'XXL' => ['XXL', 7],
            '3XL' => ['3XL', 8],
            '4XL' => ['4XL', 9],
        ];
    }

    /**
     * @dataProvider adviseProvider
     */
    public function testAdvise(string $name, int $expectedPosition): void
    {
        $advisor = new OptionPositionAdvisorSize();
        $result = $advisor($name);

        static::assertEquals($expectedPosition, $result);
    }
}
