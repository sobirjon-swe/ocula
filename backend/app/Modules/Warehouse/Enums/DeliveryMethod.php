<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Transfer qanday yetkaziladi — ENUMS.md §3, PROJECT.md 7.10.
 *
 * Usul "yo'lda" turgan qoldiqning **egasini** belgilaydi (ANALIZ 3.2):
 * haydovchi va xodim javobgar odam, taksida esa javobgar odam yo'q —
 * shuning uchun egasi transferning o'zi bo'ladi.
 *
 * Taksi xarajati **majburiy**: hozir sotuvchilar taksi pulini
 * bog'lanmagan xarajat qilib yozishadi va "qaysi yo'nalishga qancha
 * ketdi" degan savol javobsiz qoladi (7.10).
 */
enum DeliveryMethod: string
{
    /** O'z haydovchimiz — egasi haydovchi. */
    case OwnDriver = 'own_driver';

    /** Taksi — egasi transferning o'zi, xarajat majburiy. */
    case Taxi = 'taxi';

    /** Xodim o'zi olib bordi — egasi o'sha xodim. */
    case ByHand = 'by_hand';

    /**
     * Yetkazuvchi xodim ko'rsatilishi shartmi.
     */
    public function requiresCarrier(): bool
    {
        return $this !== self::Taxi;
    }

    /**
     * Xarajat summasi majburiymi (7.10).
     */
    public function requiresCost(): bool
    {
        return $this === self::Taxi;
    }

    /**
     * Transit qoldiq egasi transferning o'zimi.
     */
    public function transitIsOwnedByTransfer(): bool
    {
        return $this === self::Taxi;
    }
}
