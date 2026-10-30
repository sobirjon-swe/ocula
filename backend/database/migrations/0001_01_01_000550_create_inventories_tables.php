<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('counting');
            $table->unsignedBigInteger('snapshot_movement_id')->default(0);
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX inventories_open_location ON inventories(location_id) WHERE status IN ('counting', 'review')");
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('expected_qty');
            $table->unsignedInteger('actual_qty')->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('counted_at')->nullable();
            $table->foreignId('movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['inventory_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventories');
    }
};
