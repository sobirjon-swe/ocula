<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `customers.phone` endi nullable — BOSQICH-9.md §2.
 *
 * Telegram Mini App orqali birinchi marta kirgan mijozda telefon yo'q
 * (`initData` uni bermaydi), faqat `telegram_id` bor —
 * "ro'yxatdan o'tish formasi yo'q" (PROJECT.md §10). Xodim tomonidan
 * yaratishda `phone` hamon majburiy — bu faqat DB darajasidagi
 * bo'shliq, `CustomerRequest` o'zgarmadi.
 *
 * `unique` cheklovi saqlanadi: PostgreSQL bir nechta `NULL`ni bir-biriga
 * teng deb hisoblamaydi, shuning uchun ko'p mijozda telefon bo'sh
 * bo'lishi mumkin, ikkitasida bir xil telefon bo'lolmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN phone DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN phone SET NOT NULL');
    }
};
