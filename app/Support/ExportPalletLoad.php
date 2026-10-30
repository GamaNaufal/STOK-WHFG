<?php

namespace App\Support;

class ExportPalletLoad
{
    public const WARNING_AT = 36;
    public const DANGER_AT = 72;

    public static function level(int $boxes): string
    {
        return $boxes >= self::DANGER_AT ? 'danger' : ($boxes >= self::WARNING_AT ? 'warning' : 'normal');
    }

    public static function note(int $boxes): string
    {
        return match (self::level($boxes)) {
            'danger' => 'Perlu perhatian · ≥ '.self::DANGER_AT.' box',
            'warning' => 'Peringatan · ≥ '.self::WARNING_AT.' box',
            default => '',
        };
    }
}
