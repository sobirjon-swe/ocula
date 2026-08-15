<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Enums;

/**
 * Ish buyrug'i holati — ENUMS.md §6.
 *
 *     queued → in_progress → done
 *                          → defect   (brak — 7.5)
 *     queued → cancelled
 *
 * Bu **buyurtma holatidan alohida o'q** (7.3 bilan bir mantiq):
 * ustaxonada ish tugagani buyurtma mijozga topshirilgani degani emas.
 */
enum WorkOrderStatus: string
{
    /** Ustaxona navbatida. */
    case Queued = 'queued';

    /** Usta ishlamoqda. */
    case InProgress = 'in_progress';

    /** Tayyor. */
    case Done = 'done';

    /** Brak chiqdi (7.5). */
    case Defect = 'defect';

    case Cancelled = 'cancelled';

    public function canBeStarted(): bool
    {
        return $this === self::Queued;
    }

    public function canBeFinished(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Brak deb belgilash mumkinmi.
     *
     * Navbatda turgan ishda ham brak chiqishi mumkin: material
     * ochilganda sinib chiqqan bo'lishi mumkin.
     */
    public function canBeDefective(): bool
    {
        return $this === self::Queued || $this === self::InProgress;
    }

    /**
     * Qayta ishlashga qaytarish mumkinmi — brakdan keyin yoki
     * tayyor ish mijozga to'g'ri kelmaganda (7.3).
     */
    public function canBeReworked(): bool
    {
        return $this === self::Defect || $this === self::Done;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Queued;
    }

    /** Kanbanda ochiq ustunlarda turadimi (§10: usta ekrani). */
    public function isOpen(): bool
    {
        return $this === self::Queued || $this === self::InProgress;
    }
}
