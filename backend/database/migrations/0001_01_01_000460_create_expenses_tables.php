<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xarajatlar — SCHEMA.md, PROJECT.md §6.8, BOSQICH-10.md §10a.
 *
 * `source_type`/`source_id` polimorf (`stock_movements` uslubida) —
 * taksi xarajati kabi avtomatik yaratilgan yozuvlar manba hujjatga
 * ishora qiladi (BOSQICH-4.md §5 #4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 120)->unique();
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('description', 255)->nullable();

            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('receipt_path', 255)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'date']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
