<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yetkazib beruvchilar — SCHEMA.md §3.
 *
 * `payment_terms_days` — necha kun ichida to'lash kelishilgan (7.15
 * dagi ikki tomonlama hisob shundan boshlanadi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->string('phone', 32)->nullable();
            $table->smallInteger('payment_terms_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
