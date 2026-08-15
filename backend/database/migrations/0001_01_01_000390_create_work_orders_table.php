<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ustaxona ish buyruqlari — SCHEMA.md §6, PROJECT.md §6.6.
 *
 *     queued → in_progress → done
 *                          → defect   (brak — 7.5)
 *     queued → cancelled
 *
 * Ish buyrug'i **qo'lda yaratilmaydi**: u buyurtma ustaxonaga
 * o'tganda (`in_workshop`) tizim tomonidan tug'iladi. Shuning uchun
 * PERMISSIONS.md §6 da `work_order.create` ruxsati yo'q — faqat
 * biriktirish, boshlash, yakunlash, brak va muhimlik.
 *
 * `rework_count` — qayta ishlash soni. Usta xatosi yoki mijozga to'g'ri
 * kelmagani optikada tez-tez bo'ladi (7.3), shuning uchun u alohida
 * hujjat emas, o'sha buyruqning hisoblagichi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Navbatga tushganda usta hali tanlanmagan bo'lishi mumkin.
            $table->foreignId('master_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('status', 16);
            $table->string('priority', 8)->default('normal');

            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->smallInteger('rework_count')->default(0);
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            // Kanban ekrani aynan shu kesimda so'raydi (§10: usta).
            $table->index(['branch_id', 'status', 'priority']);
            $table->index(['master_id', 'status']);
            $table->index('order_id');
        });

        Schema::create('work_order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity');

            // Usta materialni sarflaganda yoziladi (ANALIZ 3.4:
            // `consume` — sotuv emas, lekin COGS ga tushadi).
            $table->foreignId('consumed_movement_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();

            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
    }
};
