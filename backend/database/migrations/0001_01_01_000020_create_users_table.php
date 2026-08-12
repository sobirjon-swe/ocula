<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xodimlar — SCHEMA.md §1.
 *
 * Login **telefon bo'yicha**: sotuvchi va haydovchida email bo'lmasligi
 * mumkin, shuning uchun `email` nullable.
 *
 * `pin_hash`   — planshetda tez almashish uchun (7.14).
 * `debt_limit` — direktor har bir sotuvchiga alohida belgilaydi (§15 #3);
 *                `0` = umuman qarz bera olmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('phone', 32)->unique();
            $table->string('email', 160)->nullable()->unique();
            $table->string('password');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->decimal('debt_limit', 15, 2)->default(0);
            $table->string('locale', 8)->default('uz-latn');
            $table->string('pin_hash')->nullable();
            $table->timestampTz('pin_set_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
