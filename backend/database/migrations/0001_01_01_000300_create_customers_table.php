<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mijoz — SCHEMA.md §4.
 *
 * Mijoz **butun tarmoqniki**, bitta filialniki emas: `branch_id` faqat
 * "birinchi kelgan filial" ma'nosida yoziladi va hech qanday cheklov
 * bermaydi. Shu sababli model `BelongsToBranch` ni ishlatmaydi —
 * boshqa filialda kelganda ham o'sha kartochka topilishi kerak.
 *
 * `debt_balance` — kesh (`debts` jadvalidan hisoblanadi, Finance
 * bosqichi). Bu bosqichda u 0 bo'lib turadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->string('phone', 32)->unique();
            $table->date('birth_date')->nullable();
            $table->bigInteger('telegram_id')->nullable()->unique();
            $table->string('locale', 8)->default('uz-latn');
            $table->text('notes')->nullable();
            $table->decimal('debt_balance', 15, 2)->default(0);
            $table->timestampTz('first_visit_at')->nullable();
            $table->integer('abandoned_orders_count')->default(0);

            // Birinchi kelgan filial — statistika uchun, cheklov uchun emas.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Ism bo'yicha qidiruv — katalogdagi kabi trigram (ANALIZ 3.14):
        // "Aliyev" ni "aliev" deb yozgan sotuvchi ham topsin.
        DB::statement('CREATE INDEX customers_name_trgm_idx ON customers USING GIN (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
