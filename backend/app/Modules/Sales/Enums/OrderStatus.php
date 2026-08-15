<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * Buyurtmaning bajarilish holati — ENUMS.md §4, PROJECT.md 7.3.
 *
 * Bu **to'lov holatidan mustaqil** o'q: buyurtma `ready` bo'lib, to'lovi
 * `partial` bo'lishi normal. Ikkalasini bitta ustunga siqish optikada
 * eng ko'p uchraydigan xato.
 *
 *     new → awaiting_exam → prescription_ready
 *         → materials_reserved ─┬─ (bor) ─────────────→ in_workshop
 *                               └─ (yo'q) → awaiting_transfer → in_workshop
 *         → ready → customer_notified → delivered → closed
 *
 * Yon tarmoqlar: `cancelled`, `returned`, `rework`.
 */
enum OrderStatus: string
{
    case New = 'new';
    case AwaitingExam = 'awaiting_exam';
    case PrescriptionReady = 'prescription_ready';
    case MaterialsReserved = 'materials_reserved';
    case AwaitingTransfer = 'awaiting_transfer';
    case InWorkshop = 'in_workshop';
    case Ready = 'ready';
    case CustomerNotified = 'customer_notified';

    /** Topshirildi — **daromad shu paytda tan olinadi** (7.8). */
    case Delivered = 'delivered';

    /** Yopildi: tovar ham berildi, to'lov ham tugadi. */
    case Closed = 'closed';

    case Cancelled = 'cancelled';
    case Returned = 'returned';

    /** Qayta ishlash — usta xatosi yoki mijozga to'g'ri kelmadi. */
    case Rework = 'rework';

    /**
     * Shu holatdan qo'lda o'tish mumkin bo'lgan holatlar (7.3).
     *
     * `delivered`, `cancelled` va `returned` bu ro'yxatlarda **yo'q**:
     * ular ombor va pulga tegadi, shuning uchun faqat o'z amali orqali
     * (`deliver`, `cancel`, `returns`) qo'yiladi.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::New => [self::AwaitingExam, self::PrescriptionReady, self::MaterialsReserved],
            self::AwaitingExam => [self::PrescriptionReady],
            self::PrescriptionReady => [self::MaterialsReserved],
            self::MaterialsReserved => [self::AwaitingTransfer, self::InWorkshop],
            self::AwaitingTransfer => [self::InWorkshop],
            self::InWorkshop => [self::Ready, self::Rework],
            self::Ready => [self::CustomerNotified, self::Rework],
            self::CustomerNotified => [self::Ready, self::Rework],
            self::Rework => [self::InWorkshop, self::Ready],
            self::Delivered, self::Closed, self::Cancelled, self::Returned => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }

    /**
     * Topshirish mumkinmi — tovar shu paytda ombordan chiqadi.
     *
     * `ready` va `customer_notified` — odatdagi yo'l; `new` esa tez
     * savdo uchun: chek tovar berilganda tug'iladi.
     */
    public function canBeDelivered(): bool
    {
        return match ($this) {
            self::New, self::Ready, self::CustomerNotified, self::Rework => true,
            default => false,
        };
    }

    /**
     * Bekor qilish mumkinmi — topshirilgandan keyin bekor emas,
     * **qaytarish** bo'ladi (ombor va pul allaqachon harakatlangan).
     */
    public function canBeCancelled(): bool
    {
        return match ($this) {
            self::Delivered, self::Closed, self::Cancelled, self::Returned => false,
            default => true,
        };
    }

    /** Yakuniy holatlar — bulardan keyin o'zgarish yo'q. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Closed, self::Cancelled, self::Returned => true,
            default => false,
        };
    }

    /**
     * "Bajarilmagan buyurtmalar majburiyati" ga kiradimi (7.8):
     * pul kassada, lekin tovar hali mijozda emas.
     */
    public function isOutstandingObligation(): bool
    {
        return match ($this) {
            self::Delivered, self::Closed, self::Cancelled => false,
            default => true,
        };
    }
}
