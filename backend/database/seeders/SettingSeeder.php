<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Direktor o'zgartira oladigan sozlamalar — SCHEMA.md §1.
 *
 * Kalitlar ro'yxati `SettingKey` enumida, boshlang'ich qiymatlar esa
 * `config/optika.php` da. Shu sababli bu yerda takrorlanadigan ro'yxat
 * yo'q: enumga yangi kalit qo'shilsa, u shu yerdan o'zi chiqadi.
 *
 * `firstOrCreate` — allaqachon o'zgartirilgan sozlamani standartga
 * qaytarib yubormaslik uchun.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingKey::cases() as $key) {
            Setting::query()->firstOrCreate(
                ['key' => $key->value],
                ['value' => config($key->configPath()), 'updated_at' => now()],
            );
        }
    }
}
