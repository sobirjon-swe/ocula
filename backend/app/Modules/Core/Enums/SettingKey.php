<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Direktor o'zgartira oladigan sozlamalar — SCHEMA.md §1.
 *
 * Bu enum **sozlanadigan narsalarning yagona ro'yxati**: `settings`
 * jadvaliga faqat shu kalitlar tushadi. Ochiq ro'yxat bo'lganda kimdir
 * `min_prepayment_percentt` deb yozib qo'yardi va hech kim sezmasdi —
 * eski qiymat esa jimgina ishlab yuraverardi.
 *
 * Har kalit `config/optika.php` dagi standart qiymatga ishora qiladi:
 * jadvalda yozuv bo'lmasa, o'sha qiymat ishlaydi.
 */
enum SettingKey: string
{
    /** Avans foizi — 7.7. `0` = avans majburiy emas. */
    case MinPrepaymentPercent = 'min_prepayment_percent';

    /** Yakuniy summa qaysi qadamga yaxlitlanadi — §15 #19. */
    case MoneyRoundingStep = 'money_rounding_step';

    /** Sotuvchi tasdiqsiz bera oladigan chegirma chegarasi — PERMISSIONS §4. */
    case DiscountLimitPercent = 'discount_limit_percent';

    /** Retsept necha oy amal qiladi — 7.11. */
    case PrescriptionValidityMonths = 'prescription_validity_months';

    /** Qarz eslatmasi kunlari — 7.6. */
    case DebtReminderDays = 'debt_reminder_days';

    /** Shuncha kundan keyin qarz shubhali — 7.6. */
    case DebtDoubtfulAfterDays = 'debt_doubtful_after_days';

    /** GPS mijoz manzilidan shuncha metrdan uzoq bo'lsa belgilanadi — 7.4. */
    case DeliveryGpsToleranceMeters = 'delivery_gps_tolerance_meters';

    /**
     * `config/optika.php` dagi standart qiymat yo'li.
     */
    public function configPath(): string
    {
        return 'optika.'.match ($this) {
            self::MinPrepaymentPercent => 'orders.min_prepayment_percent',
            self::MoneyRoundingStep => 'money.rounding_step',
            self::DiscountLimitPercent => 'orders.discount_limit_percent',
            self::PrescriptionValidityMonths => 'prescriptions.validity_months',
            self::DebtReminderDays => 'debts.reminder_days',
            self::DebtDoubtfulAfterDays => 'debts.doubtful_after_days',
            self::DeliveryGpsToleranceMeters => 'delivery.gps_tolerance_meters',
        };
    }

    /**
     * Qiymat uchun validatsiya qoidalari.
     *
     * Chegaralar ataylab tor: `money_rounding_step` ni 1 000 000 qilib
     * qo'yish butun narx ro'yxatini buzardi, `discount_limit_percent`
     * 100 dan oshsa esa limit ma'nosini yo'qotardi.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::MinPrepaymentPercent,
            self::DiscountLimitPercent => ['integer', 'min:0', 'max:100'],

            self::MoneyRoundingStep => ['integer', 'min:1', 'max:10000'],
            self::PrescriptionValidityMonths => ['integer', 'min:1', 'max:60'],
            self::DebtDoubtfulAfterDays => ['integer', 'min:1', 'max:3650'],
            self::DeliveryGpsToleranceMeters => ['integer', 'min:0', 'max:100000'],
            self::DebtReminderDays => ['array', 'max:20'],
        };
    }

    /**
     * Massiv qiymatli kalitlar uchun element qoidalari (`key.*`).
     *
     * @return array<int, string>
     */
    public function itemRules(): array
    {
        return match ($this) {
            self::DebtReminderDays => ['integer', 'min:-30', 'max:365'],
            default => [],
        };
    }

    public function isList(): bool
    {
        return $this === self::DebtReminderDays;
    }
}
