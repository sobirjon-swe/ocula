<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Linza sindirish ko'rsatkichi — ENUMS.md §2.
 *
 * Bazada `decimal(3,2)`, ilova darajasida shu enum bilan validatsiya
 * qilinadi (`Rule::enum()`).
 */
enum LensIndex: string
{
    case Index150 = '1.50';
    case Index156 = '1.56';
    case Index161 = '1.61';
    case Index167 = '1.67';
    case Index174 = '1.74';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
