<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram bot — SCHEMA.md ga qo'shimcha, PROJECT.md §11 (Bosqich 7).
 *
 * `telegram_link_codes` — mijozni botga bog'lash uchun bir martalik kod
 * (BOSQICH-7.md §3). Mini App (Bosqich 9) hali yo'q, shuning uchun
 * bog'lash eng oddiy yo'l bilan: sotuvchi kod yaratadi, mijoz botga
 * `/start <kod>` yuboradi.
 *
 * `telegram_notifications` — yuborilgan har bir xabar jurnali. Qarz va
 * ko'rik eslatmalari kuniga bir marta ishga tushadigan buyruqlardan
 * keladi — `dedupe_key` shu buyruq ikki marta ishga tushsa ham xabar
 * ikki marta ketmasligini kafolatlaydi (BOSQICH-7.md §5 #2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_link_codes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            // **Global** unique emas ataylab: kod eskirgach (ishlatilgan
            // yoki muddati o'tgan) qiymat qayta berilishi mumkin bo'lishi
            // kerak, aks holda ko'p oylik ishlatilishda vaqti-vaqti bilan
            // to'qnashuv sodir bo'lib, generatsiya butunlay to'xtab qolardi.
            // Bir martalikni `GenerateTelegramLinkCode` faol kodlar orasida,
            // to'g'ri kodni esa `LinkCustomerByCode` `isUsable()` bilan
            // kafolatlaydi.
            $table->string('code', 6);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index('code');
            $table->index(['customer_id', 'used_at']);
        });

        Schema::create('telegram_notifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->bigInteger('chat_id');

            $table->string('type', 32);

            // Manba hujjat — polimorf, `stock_movements` uslubida (SCHEMA.md §3).
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Avtomatik (scheduled) eslatmalar shu bilan bir martalik
            // bo'ladi. Qo'lda yuborilgan xabarlarda `null` — ular
            // dublikat tekshiruvidan ataylab chetlanadi.
            $table->string('dedupe_key', 191)->nullable()->unique();

            $table->text('message');
            $table->string('status', 16);
            $table->text('error')->nullable();

            $table->timestampsTz();

            $table->index(['type', 'source_type', 'source_id']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_notifications');
        Schema::dropIfExists('telegram_link_codes');
    }
};
