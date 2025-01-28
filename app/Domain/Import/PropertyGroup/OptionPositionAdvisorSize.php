<?php

namespace App\Domain\Import\PropertyGroup;

class OptionPositionAdvisorSize implements OptionPositionAdvisor
{
    public function __invoke(string $optionName): int
    {
        $methods = [
            $this->handleRegularNumber(...),
            $this->handleShirtSize(...),
            $this->handleSuffixedNumber(...),
            $this->handlePartialNumber(...),
        ];

        foreach ($methods as $method) {
            if (!is_null($result = $method($optionName)))
                return $result;
        }

        return 1;
    }

    public function handleRegularNumber(string $value): ?int
    {
        $value = str_replace(',', '.', $value);

        if (is_numeric($value))
            return 10_000 + ($value * 10);

        return null;
    }

    public function handleSuffixedNumber(string $value): ?int
    {
        if (preg_match('/^.*?(\d+)([a-z]+).*?$/i', $value, $matches) === 0)
            return null;

        $number = $matches[1];
        $suffix = $matches[2];

        $suffixParts = preg_split('//', $suffix, -1, PREG_SPLIT_NO_EMPTY);
        $suffixValue = collect($suffixParts)
            ->reduce(fn(int $result, string $part): int => $result + mb_ord(mb_strtoupper($part)), 0);

        return 100_000 + ($number * 1000) + $suffixValue;
    }

    public function handlePartialNumber(string $value): ?int
    {
        if (preg_match('/^.*?(\d+)\s+(\d+)\/(\d+).*?$/i', $value, $matches) === 0)
            return null;

        $number = $matches[1];
        $partialA = $matches[2];
        $partialB = $matches[3];

        return 1_000_000 + ($number * 10_000) + (int) (($partialA / $partialB) * 1_000);
    }

    public function handleShirtSize(string $value): ?int
    {
        static $mapping = [
            'XXS' => 1,
            'XS' => 2,
            'S' => 3,
            'M' => 4,
            'L' => 5,
            'XL' => 6,
            'XXL' => 7,
            '2XL' => 7,
        ];

        if ($result = $mapping[$value] ?? null)
            return $result;

        if (preg_match('/^.*?(\d+)xl.*?$/i', $value, $matches) === 0)
            return null;

        return (int) $matches[1] + $mapping['L'];
    }
}
