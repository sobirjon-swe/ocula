<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategoriyalar — SCHEMA.md §2.
 *
 * Kategoriya nomi **tarjima qilinadi** (PROJECT.md §10) — tovar nomidan
 * farqli o'laroq. Tarjima `name` jsonb ustunida emas, `lang/` fayllarida
 * emas, balki keyingi bosqichda `spatie/laravel-translatable` bilan
 * qo'shiladi; hozircha bitta nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
