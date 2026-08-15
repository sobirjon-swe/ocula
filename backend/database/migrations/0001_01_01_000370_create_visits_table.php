<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ko'rik vizitlari va navbat — SCHEMA.md §5, PROJECT.md §6.5.
 *
 * Vizit QR orqali (mijoz o'zi), sotuvchi orqali yoki shunchaki kirib
 * kelgan mijoz uchun ochiladi. Navbat raqami **filial va kun**
 * kesimida beriladi: ertaga yana 1 dan boshlanadi.
 *
 * `queue_date` — ataylab oddiy ustun, generated column emas:
 * `created_at::date` PostgreSQL da immutable emas (vaqt mintaqasiga
 * bog'liq) va generated column'da ishlatib bo'lmaydi. Qiymatni ilova
 * qo'yadi, noyoblikni esa indeks kafolatlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // Navbatga qo'shilganda shifokor hali tanlanmagan bo'lishi mumkin.
            $table->foreignId('doctor_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->smallInteger('queue_number');
            $table->date('queue_date');

            $table->string('status', 16);
            $table->string('source', 16);

            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            // Bir kunda bir filialda bir raqam ikki marta berilmaydi.
            $table->unique(['branch_id', 'queue_date', 'queue_number']);

            $table->index(['branch_id', 'status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
