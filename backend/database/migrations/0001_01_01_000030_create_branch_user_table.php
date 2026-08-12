<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xodimning qo'shimcha filiallari — SCHEMA.md §1, ANALIZ 3.11.
 *
 * `users.branch_id` asosiy filial. Haydovchi, omborchi va filiallar
 * orasida ko'chib yuruvchi sotuvchi uchun shu pivot ishlatiladi.
 * Bosqich 1 da bo'sh bo'lishi mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->primary(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
    }
};
