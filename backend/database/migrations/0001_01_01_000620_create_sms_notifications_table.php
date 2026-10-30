<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('dedupe_key')->nullable()->unique();
            $table->string('provider_message_id')->unique();
            $table->string('phone', 20);
            $table->string('type', 40);
            $table->text('message');
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notifications');
    }
};
