<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO qatlamlari — SCHEMA.md §3, PROJECT.md 7.20.
 *
 * Har bir kirim alohida qatlam ochadi. Chiqim qatlamlarni `received_at`
 * bo'yicha eng eskisidan sarflaydi — sotilgan tovarning tannarxi (COGS)
 * shundan chiqadi.
 *
 * `quantity_remaining` — bu jadvaldagi **yagona o'zgaradigan ustun**
 * (7.21 dagi "tahrirlanmaydi" qoidasidan ataylab qilingan istisno).
 * Sarflash tarixi `stock_layer_consumptions` da to'liq saqlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_layers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->string('source_type', 32);
            $table->bigInteger('source_id')->nullable();
            $table->foreignId('movement_id')->constrained('stock_movements')->restrictOnDelete();

            $table->decimal('unit_cost', 15, 2);
            $table->integer('quantity_in');
            $table->integer('quantity_remaining');
            $table->timestampTz('received_at');
            $table->timestampTz('created_at');
        });

        // FIFO tanlash uchun asosiy indeks: sarflanib bo'lgan qatlamlar
        // qidiruvga umuman kirmaydi.
        DB::statement(
            'CREATE INDEX layers_fifo_idx ON stock_layers
               (variant_id, location_id, received_at, id) WHERE quantity_remaining > 0'
        );
        DB::statement('CREATE INDEX layers_movement_idx ON stock_layers (movement_id)');

        DB::statement(
            'ALTER TABLE stock_layers ADD CONSTRAINT layers_remaining_in_range
               CHECK (quantity_remaining >= 0 AND quantity_remaining <= quantity_in)'
        );
        DB::statement(
            'ALTER TABLE stock_layers ADD CONSTRAINT layers_quantity_in_positive
               CHECK (quantity_in > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layers');
    }
};
