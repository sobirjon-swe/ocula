<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Kassa harakati toifasi — ENUMS.md §8.
 *
 * Toifa kunlik kassa hisobotidagi qatorni belgilaydi (7.8-A). Deyarli
 * hammasining yo'nalishi qat'iy: `sale` faqat kirim, `refund` faqat
 * chiqim bo'ladi. Yagona istisno — `correction`: u storno yozuvi va
 * ikkala tomonga ham ketishi mumkin.
 */
enum CashCategory: string
{
    /** Savdodan naqd tushum — `Payment`. */
    case Sale = 'sale';

    /** Eski qarzning naqd to'lovi — `Payment`. */
    case DebtPayment = 'debt_payment';

    /** Haydovchidan yig'ilgan pul qabul qilindi (7.4). */
    case Collection = 'collection';

    /** Smena boshidagi naqd — `Shift`. */
    case ShiftOpening = 'shift_opening';

    /** Mijozga pul qaytarildi — `OrderReturn`. */
    case Refund = 'refund';

    /** Xarajat — `Expense` (Finance bosqichi). */
    case Expense = 'expense';

    /** Yetkazib beruvchiga to'lov (7.15). */
    case SupplierPayment = 'supplier_payment';

    /** Oylik. */
    case Salary = 'salary';

    /** Mukofot to'landi (7.12). */
    case BonusPayout = 'bonus_payout';

    /** Seyfga/bankka topshirildi — inkassatsiya. */
    case DepositToSafe = 'deposit_to_safe';

    /** Smena yopilishidagi kamomad. */
    case Shortage = 'shortage';

    /** Smena yopilishidagi ortiqcha. */
    case Surplus = 'surplus';

    /** Storno (7.21) — yo'nalishi asl yozuvga teskari. */
    case Correction = 'correction';

    /**
     * Toifaning qat'iy yo'nalishi. `null` — ikkalasi ham mumkin.
     */
    public function direction(): ?CashDirection
    {
        return match ($this) {
            self::Sale, self::DebtPayment, self::Collection,
            self::ShiftOpening, self::Surplus => CashDirection::In,

            self::Refund, self::Expense, self::SupplierPayment, self::Salary,
            self::BonusPayout, self::DepositToSafe, self::Shortage => CashDirection::Out,

            self::Correction => null,
        };
    }

    /**
     * Xodim kassa ekranidan qo'lda yoza oladigan toifalar.
     *
     * Sotuv, qaytarish va smena yozuvlari qo'lda yozilmaydi — ular
     * o'z amalidan avtomatik tug'iladi, aks holda daftar hujjat bilan
     * ajralib ketardi.
     *
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [
            self::Collection, self::Expense, self::SupplierPayment,
            self::Salary, self::BonusPayout, self::DepositToSafe,
        ];
    }

    public function isManual(): bool
    {
        return in_array($this, self::manual(), true);
    }
}
