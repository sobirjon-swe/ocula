<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotentlik kalitlari — SCHEMA.md §1, PROJECT.md §9, ANALIZ 3.7.
 *
 * Internet uzilib so'rov qayta yuborilganda ikkinchi hujjat yaratilmasin.
 * Bir xil `key` + boshqa `request_hash` -> 409 Conflict.
 * Amal qilish muddati 24 soat (`expires_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('endpoint', 160);
            $table->string('request_hash', 64);
            $table->jsonb('response_body')->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
