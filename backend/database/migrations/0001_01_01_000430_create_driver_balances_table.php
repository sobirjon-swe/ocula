<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haydovchi qo'lidagi pul — PROJECT.md §5.2, 7.4, BOSQICH-8.md §3.
 *
 * Tovar tomoni (transit location) Bosqich 4'da qurilgan; bu jadvallar
 * **pul** tomonini qo'shadi. `driver_balances` — kesh, haqiqat manbai
 * `SUM(pending to'lovlar) − SUM(inkassatsiyalar)` (SCHEMA.md).
 *
 * `collections` — haydovchi kassaga pul topshirgan hujjat, insert-only
 * (moliyaviy hujjat, 7.21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_balances', function (Blueprint $table): void {
            $table->foreignId('driver_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->decimal('cash_amount', 15, 2)->default(0);
            $table->timestampTz('updated_at');
        });

        Schema::create('collections', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at');

            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
        Schema::dropIfExists('driver_balances');
    }
};
