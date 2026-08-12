<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filiallar — SCHEMA.md §1.
 *
 * `code` hujjat raqamining birinchi bo'lagi (`A-2608-00147`), shuning
 * uchun qisqa va noyob.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('code', 8)->unique();
            $table->string('type', 16);
            $table->string('address', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
