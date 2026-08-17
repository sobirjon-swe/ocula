<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `transfers.trip_id` endi haqiqiy tashqi kalit — BOSQICH-8.md §2.
 *
 * Ustun Bosqich 4'da tashqi kalitsiz qo'yilgan edi, chunki `trips`
 * hali yo'q edi (`orders.prescription_id` bilan bir xil sabab —
 * BOSQICH-4.md §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table): void {
            $table->foreign('trip_id')->references('id')->on('trips')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table): void {
            $table->dropForeign(['trip_id']);
        });
    }
};
