<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yo'qotilgan savdo — SCHEMA.md, PROJECT.md 7.9, ANALIZ.md 3.8,
 * BOSQICH-10.md §10c.
 *
 * `variant_id` **yoki** `search_term` — sotuvchi tovarni topolmaganda
 * katalogda umuman yo'q bo'lishi ham mumkin, o'shanda `variant_id`
 * yo'q. `search_term` — analitik log, ombor yozuvi emas, shuning
 * uchun 7.13 dagi "erkin matn ishlatilmaydi" qoidasiga zid emas (3.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_sales', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('search_term', 160)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reason', 24);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['branch_id', 'created_at']);
            $table->index('variant_id');
        });

        DB::statement(
            'ALTER TABLE lost_sales ADD CONSTRAINT lost_sales_variant_or_term '
            .'CHECK (variant_id IS NOT NULL OR search_term IS NOT NULL)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_sales');
    }
};
