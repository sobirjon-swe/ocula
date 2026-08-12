<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xizmatlar — SCHEMA.md §2.
 *
 * Ko'rik, linza o'rnatish, ta'mir, sozlash. Xizmat ombordan o'tmaydi,
 * lekin chekka va buyurtmaga `order_items` orqali tushadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->decimal('price', 15, 2);
            $table->smallInteger('duration_min')->nullable();
            $table->string('type', 16);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
