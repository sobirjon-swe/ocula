<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onlayn navbat so'rovi — PROJECT.md §11 (Bosqich 11), BOSQICH-11.md.
 *
 * Landing saytdan (mehmon, hisobi yo'q) kelgan "menga qo'ng'iroq
 * qiling" so'rovi. Real navbatga (`visits`) **to'g'ridan-to'g'ri
 * yozilmaydi** — u xodim tasdiqlagandan keyin, real mijoz bilan
 * qo'lda ochiladi. Sabab: `visits.queue_number`/`queue_date` bugungi
 * jismoniy navbat uchun mo'ljallangan, kelajakdagi onlayn band qilish
 * uchun emas — ikkalasini aralashtirish navbatni buzardi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('phone', 32);
            $table->date('preferred_date')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 16)->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('handled_at')->nullable();
            $table->timestampsTz();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
