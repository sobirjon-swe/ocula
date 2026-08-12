<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qoldiq keshi — SCHEMA.md §3, PROJECT.md 7.1.
 *
 * **Faqat hosila.** Haqiqat manbai — `stock_movements`. Bu jadval
 * `php artisan stock:rebuild-balances` bilan har doim qayta hisoblanadi;
 * ular ajralib qolsa, ayb keshda, daftarda emas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestampTz('updated_at');

            $table->primary(['location_id', 'variant_id']);
            $table->index(['branch_id', 'variant_id']);
        });

        // Qidiruvda ko'pincha "qayerda bor" so'raladi — nol qoldiqlar
        // indeksda o'rin egallamasin.
        DB::statement('CREATE INDEX balances_available_idx ON stock_balances (variant_id) WHERE quantity > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
