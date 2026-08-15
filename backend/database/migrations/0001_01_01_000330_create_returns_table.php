<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qaytarish — SCHEMA.md §4 (3.6).
 *
 * Har bir satr alohida hal qilinadi: biri omborga qaytadi
 * (`restock = true` → `stock_movements(type = return)` va sotilgan
 * tannarx bilan yangi FIFO qatlami), boshqasi brakka ketadi.
 *
 * `cost_total` — qaytgan tovarning tannarxi. Foyda hisobotida sotuv
 * ham, tannarx ham teskari yozilishi kerak, aks holda qaytarilgan
 * tovar foydani ko'tarib turaverardi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('number', 32)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();

            $table->string('reason', 32);
            $table->decimal('amount', 15, 2);
            $table->decimal('cost_total', 15, 2)->default(0);

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'created_at']);
            $table->index('order_id');
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();

            // Xizmat qaytsa variant yo'q — ombor ham tegmaydi.
            $table->foreignId('variant_id')->nullable()
                ->constrained('product_variants')->restrictOnDelete();

            $table->integer('quantity');
            $table->decimal('amount', 15, 2);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->boolean('restock')->default(true);
            $table->foreignId('movement_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
