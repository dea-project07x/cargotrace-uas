<?php

namespace App\Enums;

enum Severity: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
