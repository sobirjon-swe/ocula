<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filial oylik rejasi — SCHEMA.md §9, PROJECT.md 7.18, BOSQICH-10.md §10b.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_plans', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('period', 7);
            $table->string('type', 16);
            $table->decimal('target_amount', 15, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['branch_id', 'period', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_plans');
    }
};
