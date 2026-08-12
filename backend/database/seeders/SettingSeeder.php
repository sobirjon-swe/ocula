<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Direktor o'zgartira oladigan sozlamalar — SCHEMA.md §1.
 *
 * Boshlang'ich qiymatlar `config/optika.php` dan olinadi, keyin direktor
 * ularni interfeysdan o'zgartiradi. Masalan 7.7: avans hozir talab
 * qilinmaydi (`0`), lekin mexanizm tayyor turadi.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'min_prepayment_percent' => config('optika.orders.min_prepayment_percent'),
            'money_rounding_step' => config('optika.money.rounding_step'),
            'prescription_validity_months' => config('optika.prescriptions.validity_months'),
            'debt_reminder_days' => config('optika.debts.reminder_days'),
            'debt_doubtful_after_days' => config('optika.debts.doubtful_after_days'),
            'delivery_gps_tolerance_meters' => config('optika.delivery.gps_tolerance_meters'),
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()],
            );
        }
    }
}
