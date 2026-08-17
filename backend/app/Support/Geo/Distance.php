<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Ikki koordinata orasidagi masofa — PROJECT.md 7.4.
 *
 * GPS mijoz manzilidan uzoq bo'lsa belgilanadi (ayblash uchun emas,
 * hujjat uchun) — chegara `SettingKey::DeliveryGpsToleranceMeters`.
 */
final class Distance
{
    private const float EARTH_RADIUS_METERS = 6_371_000.0;

    /**
     * Haversine formulasi — kichik masofalar uchun yetarli aniqlikda.
     */
    public static function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round(self::EARTH_RADIUS_METERS * $c);
    }
}
