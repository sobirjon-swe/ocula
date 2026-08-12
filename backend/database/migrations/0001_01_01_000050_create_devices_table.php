<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ro'yxatdan o'tgan qurilmalar — SCHEMA.md §1, PROJECT.md 7.14.
 *
 * Planshet qurilma sifatida bir marta ro'yxatdan o'tadi, xodim 4 xonali
 * PIN bilan almashadi — shunda har amal aniq `user_id` ga yoziladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('type', 16);
            $table->string('token', 128)->unique();
            $table->jsonb('allowed_roles')->default('[]');
            $table->timestampTz('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
