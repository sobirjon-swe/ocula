<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smenalar — SCHEMA.md §1.
 *
 * Kunlik hisobot shu jadval bo'yicha, kalendar sanasi bo'yicha emas
 * (§15 #24). Filialda bir vaqtda faqat bitta ochiq smena bo'lishi
 * mumkin — partial unique indeks buni majburlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('actual_cash', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->nullable();
            $table->string('status', 16);
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'opened_at']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX shifts_one_open_per_branch
               ON shifts (branch_id)
               WHERE status IN ('open', 'closing')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
