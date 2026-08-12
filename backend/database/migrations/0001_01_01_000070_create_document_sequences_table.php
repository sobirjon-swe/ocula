<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hujjat raqamlari hisoblagichi — SCHEMA.md §1, ANALIZ 3.12.
 *
 * Format: `{BRANCH_CODE}-{YY}{MM}-{NNNNN}` -> `A-2608-00147`.
 * Generatsiya `App\Support\Documents\DocumentNumber` da, tranzaksiya
 * ichida `SELECT ... FOR UPDATE` bilan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('period', 4);
            $table->integer('last_number')->default(0);
            $table->timestampsTz();

            $table->unique(['branch_id', 'type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
