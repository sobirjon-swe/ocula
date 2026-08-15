<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Enums;

/**
 * Vizit holati — ENUMS.md §5.
 *
 *     waiting → in_progress → finished
 *             → no_show
 *     waiting → cancelled
 *
 * `no_show` va `cancelled` ataylab ajratilgan: birinchisi "mijoz
 * kelmadi", ikkinchisi "vizit bekor qilindi". Hisobotda ular bir
 * qatorga tushsa, shifokorning bo'sh o'tirgan vaqti ko'rinmay qolardi.
 */
enum VisitStatus: string
{
    /** Navbatda kutmoqda. */
    case Waiting = 'waiting';

    /** Ko'rik ketmoqda. */
    case InProgress = 'in_progress';

    /** Ko'rik tugadi. */
    case Finished = 'finished';

    /** Mijoz kelmadi. */
    case NoShow = 'no_show';

    case Cancelled = 'cancelled';

    /**
     * Ko'rikni boshlash mumkinmi.
     */
    public function canBeStarted(): bool
    {
        return $this === self::Waiting;
    }

    /**
     * Yakunlash mumkinmi.
     */
    public function canBeFinished(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Bekor qilish yoki "kelmadi" deb belgilash mumkinmi.
     *
     * Boshlangan ko'rikni bekor qilib bo'lmaydi — u yakunlanadi,
     * aks holda shifokor sarflagan vaqt hisobdan yo'qolardi.
     */
    public function canBeAbandoned(): bool
    {
        return $this === self::Waiting;
    }

    /** Navbatda turgan holatlar — shifokor ekranidagi jonli ro'yxat. */
    public function isOpen(): bool
    {
        return $this === self::Waiting || $this === self::InProgress;
    }

    public function isFinal(): bool
    {
        return ! $this->isOpen();
    }
}
