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
            fn (string $optionName): int => 2**31 - 1,
        ];

        foreach ($methods as $method) {
            if (!is_null($result = $method($optionName)))
                break;
        }

        return min($result, 2**31 - 1);
    }

    public function handleRegularNumber(string $value): ?int
    {
        $value = str_replace(',', '.', $value);

        if (is_numeric($value))
            return $value * pow(10, 7);

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
            ->reduce(fn(int $result, string $part): int => $result + $this->convertToAlphaPosition($part), 0);

        return $number * pow(10, 7)
            + $suffixValue * pow(10, 3);
    }

    private function convertToAlphaPosition(string $char): int
    {
        $char = mb_strtoupper($char);

        return mb_ord($char) - mb_ord('A') + 1;
    }

    public function handlePartialNumber(string $value): ?int
    {
        if (preg_match('/^.*?(\d+)\s+(\d+)\/(\d+).*?$/i', $value, $matches) === 0)
            return null;

        $number = $matches[1];
        $partialA = $matches[2];
        $partialB = $matches[3];

        return $number * pow(10, 7)
            + (int) (($partialA / $partialB) * 1_000);
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
