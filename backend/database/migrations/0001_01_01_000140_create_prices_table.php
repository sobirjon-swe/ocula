<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narxlar — SCHEMA.md §2, ANALIZ 3.16.
 *
 * Qaysi narx yutadi:
 * 1. filial narxi (`branch_id = :branch`), `valid_from <= now()`,
 *    `valid_to` null yoki kelajakda;
 * 2. topilmasa — global narx (`branch_id IS NULL`) xuddi shu shart bilan;
 * 3. ikkalasidan ham eng katta `valid_from`.
 *
 * Yangi narx qo'yilganda avvalgisining `valid_to` avtomatik yopiladi —
 * shunda narx tarixini tiklash mumkin bo'ladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['variant_id', 'branch_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
