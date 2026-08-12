<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kirim hujjati — SCHEMA.md §3, PROJECT.md 7.20.
 *
 *     draft → received   (qatlamlar ochiladi)
 *     draft → cancelled
 *
 * `received` dan orqaga qaytish yo'q — xato bo'lsa storno (7.21).
 * Shuning uchun `received_at`/`received_by` bir marta yoziladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('number', 32)->unique();
            $table->date('date');
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 16);
            $table->timestampTz('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['supplier_id', 'date']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('cost_price', 15, 2);
            $table->decimal('total', 15, 2);

            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
