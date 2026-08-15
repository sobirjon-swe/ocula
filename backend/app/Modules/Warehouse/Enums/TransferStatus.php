<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Transfer holati — ENUMS.md §3, PROJECT.md 7.4.
 *
 *     draft → sent → in_transit → received
 *                               → partially_received
 *     draft → cancelled
 *
 * Qoldiq qayerdaligi holatdan kelib chiqadi: `draft` da jo'natuvchida,
 * `sent`/`in_transit` da transitda, `received` dan keyin qabul
 * qiluvchida. Shuning uchun holatni qo'lda "tuzatib" qo'yish mumkin
 * emas — har o'tish o'z amali orqali bo'ladi.
 */
enum TransferStatus: string
{
    /** Tayyorlanmoqda — ombor hali tegilmagan. */
    case Draft = 'draft';

    /** Jo'natildi — tovar transitda. */
    case Sent = 'sent';

    /** Yo'lda — haydovchi reysiga bog'landi (Bosqich 8). */
    case InTransit = 'in_transit';

    /** To'liq qabul qilindi. */
    case Received = 'received';

    /** Jo'natilganidan kam keldi — farq hisobdan chiqarildi (7.4). */
    case PartiallyReceived = 'partially_received';

    case Cancelled = 'cancelled';

    /**
     * Tovar shu holatda transitda turadimi.
     */
    public function isOnTheRoad(): bool
    {
        return $this === self::Sent || $this === self::InTransit;
    }

    /**
     * Qabul qilish mumkinmi.
     */
    public function canBeReceived(): bool
    {
        return $this->isOnTheRoad();
    }

    /**
     * Bekor qilish faqat qoralamadan: jo'natilgan tovar allaqachon
     * ombordan chiqqan, uni "bekor qilish" qoldiqni yo'qotardi.
     */
    public function canBeCancelled(): bool
    {
        return $this === self::Draft;
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Received, self::PartiallyReceived, self::Cancelled => true,
            default => false,
        };
    }
}
