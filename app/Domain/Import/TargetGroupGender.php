<?php

namespace App\Domain\Import;

enum TargetGroupGender: string {
    case Male = 'male';
    case Female = 'female';
    case Child = 'child';

    public static function tryFromFedas(string $fedas): ?self
    {
        $lastDigit = substr($fedas, -1, 1);

        return match (intval($lastDigit)) {
            1, 4, 7 => TargetGroupGender::Male,
            2, 5, 8 => TargetGroupGender::Female,
            3, 6, 9 => TargetGroupGender::Child,
            default => null,
        };
    }

    public static function tryFromWarengruppe(string $warengruppe): ?self
    {
        $lastDigit = substr($warengruppe, -1, 1);

        return match (strtolower($lastDigit)) {
            'h' => TargetGroupGender::Male,
            'd' => TargetGroupGender::Female,
            'k' => TargetGroupGender::Child,
            default => null,
        };
    }
}
