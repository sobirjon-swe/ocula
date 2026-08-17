<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yetkazib beruvchi bilan ikki tomonlama hisob — SCHEMA.md, PROJECT.md
 * 7.15, BOSQICH-10.md §10a.
 *
 * Insert-only (7.21): balans shu yozuvlar yig'indisi. `amount` ishorali
 * — `+` avans (yetkazib beruvchi tovar qarzdor), `−` biz pul qarzdormiz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_transactions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 15, 2);
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('supplier_transactions')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['supplier_id', 'created_at']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
    }
};
