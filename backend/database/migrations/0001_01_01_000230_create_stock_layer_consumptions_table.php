<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qatlam sarflashlari — SCHEMA.md §3, PROJECT.md 7.20.
 *
 * `stock_movements.cost_total` = shu jadvaldagi `total_cost` yig'indisi.
 *
 * **Insert-only** (7.21). Storno qilinganda **manfiy** yozuv qo'shiladi
 * va `stock_layers.quantity_remaining` qaytariladi — asl yozuv o'z
 * joyida qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_layer_consumptions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('layer_id')->constrained('stock_layers')->restrictOnDelete();
            $table->foreignId('movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->timestampTz('created_at');

            $table->index('movement_id');
            $table->index('layer_id');
        });

        DB::statement(
            'ALTER TABLE stock_layer_consumptions ADD CONSTRAINT slc_quantity_not_zero
               CHECK (quantity <> 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layer_consumptions');
    }
};
