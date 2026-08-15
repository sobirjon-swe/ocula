<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * Satr tannarxi qayerdan olingan — SCHEMA.md §4 (3.9), PROJECT.md 7.20.
 *
 * Bu ustun foyda hisobotining halolligi uchun: `none` bo'lgan satrlar
 * "tannarxsiz sotuv" qatorida alohida ko'rsatiladi. Aks holda ular
 * 100% foyda bo'lib ko'rinib, umumiy foydani ham, mukofotni ham
 * soxta yuqori chiqarardi (7.12).
 */
enum CostSource: string
{
    /** Ombordagi variant — tannarx FIFO qatlamlaridan (7.20). */
    case Fifo = 'fifo';

    /** Individual linza — tannarx qo'lda kiritiladi (3.9). */
    case Manual = 'manual';

    /** Xizmat — tannarx yo'q. */
    case None = 'none';

    /**
     * Ombordan o'tadimi — shu tur satrlar topshirishda chiqim yozadi.
     */
    public function touchesStock(): bool
    {
        return $this === self::Fifo;
    }
}
