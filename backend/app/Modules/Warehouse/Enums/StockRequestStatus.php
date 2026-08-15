<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Ichki so'rov holati — ENUMS.md §3.
 *
 *     pending → approved → fulfilled
 *             → rejected
 *     pending → cancelled
 *
 * `fulfilled` — so'rovdan transfer tug'ilgan payt. Undan keyin so'rovni
 * o'zgartirib bo'lmaydi: tovar allaqachon yo'lga chiqqan.
 */
enum StockRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    /** Tasdiqlash yoki rad etish mumkinmi. */
    public function canBeDecided(): bool
    {
        return $this === self::Pending;
    }

    /** So'rovdan transfer yaratish mumkinmi. */
    public function canBeFulfilled(): bool
    {
        return $this === self::Approved;
    }

    /** So'ragan filial bekor qila oladimi. */
    public function canBeCancelled(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
