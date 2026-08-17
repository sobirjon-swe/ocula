<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mukofot yozuvi — SCHEMA.md §9, PROJECT.md 7.12, BOSQICH-10.md §10b.
 *
 * Insert-only (7.21): `updated_at` yo'q, tuzatish faqat storno
 * (`reverses_id`) orqali. Qisman unique indeks — bitta buyurtma bitta
 * qoida bo'yicha bitta faol (storno qilinmagan) yozuvga ega bo'ladi,
 * shu bilan qayta hisoblash ikki marta yozib qo'ymaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('bonus_rules')->nullOnDelete();
            $table->string('period', 7);
            $table->decimal('base_amount', 15, 2);
            $table->decimal('percent', 5, 2);
            $table->decimal('amount', 15, 2);
            $table->string('status', 16);
            $table->foreignId('reverses_id')->nullable()->constrained('bonus_entries')->nullOnDelete();
            $table->timestampTz('calculated_at');
            $table->timestampTz('created_at');

            $table->index(['user_id', 'period']);
            $table->index('order_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX bonus_entries_order_user_rule_active '
            .'ON bonus_entries (order_id, user_id, rule_id) WHERE reverses_id IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_entries');
    }
};
