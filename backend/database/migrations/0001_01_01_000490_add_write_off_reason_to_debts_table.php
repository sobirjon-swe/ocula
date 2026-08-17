<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hisobdan chiqarish sababi — BOSQICH-10.md §10a.
 *
 * Moliyaviy yozib tashlash asossiz bo'lmasligi kerak — sabab
 * `POST /debts/{id}/write-off` da majburiy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->string('write_off_reason', 255)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->dropColumn('write_off_reason');
        });
    }
};
