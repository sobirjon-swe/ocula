<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Enums;

/**
 * Ish muhimligi — ENUMS.md §6.
 *
 * Kanbanda tartib shu bo'yicha: shoshilinch ish tepada turadi, aks
 * holda usta ro'yxatning oxiridagi kechikkan buyurtmani ko'rmay
 * qolardi (§10 — rang: kechikkan qizil, bugun sariq).
 */
enum WorkOrderPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Tartiblash og'irligi — kattasi tepada.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 3,
            self::Normal => 2,
            self::Low => 1,
        };
    }
}
