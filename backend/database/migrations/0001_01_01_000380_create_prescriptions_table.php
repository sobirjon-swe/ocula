<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retsept va tiket — SCHEMA.md §5, PROJECT.md 7.11.
 *
 * Retsept saqlanganda sotuvchida **avtomatik tiket** ochiladi. Tiket
 * alohida jadval emas: u shu qatorning `ticket_active` +
 * `ticket_branch_id` juftligi.
 *
 * **7.11 kritik qoida:** faol tiket faqat `ticket_branch_id` filialida
 * ko'rinadi, retsept **tarixi** esa barcha filiallarda o'qiladi. Sabab:
 * A filialdagi shifokorning retsepti B filialga tushsa, B da keraksiz
 * ko'zoynak yasalib qoladi — sof brak va zarar.
 *
 * Diopter ustunlari `decimal(4,2)`: −20.00 dan +20.00 gacha, 0.25
 * qadam bilan. Qadam ilova darajasida tekshiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // Retsept vizitsiz ham yozilishi mumkin (eski qog'ozni kiritish).
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // Yozilgan filial — tarixda qaysi filial yozgani ko'rinsin.
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();

            // O'ng ko'z (oculus dexter).
            $table->decimal('od_sph', 4, 2)->nullable();
            $table->decimal('od_cyl', 4, 2)->nullable();
            $table->smallInteger('od_axis')->nullable();
            $table->decimal('od_add', 4, 2)->nullable();

            // Chap ko'z (oculus sinister).
            $table->decimal('os_sph', 4, 2)->nullable();
            $table->decimal('os_cyl', 4, 2)->nullable();
            $table->smallInteger('os_axis')->nullable();
            $table->decimal('os_add', 4, 2)->nullable();

            $table->decimal('pd', 4, 1)->nullable();
            $table->decimal('pd_near', 4, 1)->nullable();
            $table->string('prism', 40)->nullable();
            $table->text('notes')->nullable();

            $table->date('valid_until');

            // Tiket — sotuvchining ish hujjati (7.11).
            $table->boolean('ticket_active')->default(true);
            $table->foreignId('ticket_branch_id')->constrained('branches')->restrictOnDelete();

            $table->timestampsTz();

            $table->index(['customer_id', 'created_at']);
        });

        // Faol tiketlar ro'yxati sotuvchi ekranida har kirganda
        // so'raladi — qisman indeks aynan shuning uchun.
        DB::statement(
            'CREATE INDEX prescriptions_active_ticket_idx
               ON prescriptions (ticket_branch_id, ticket_active)
             WHERE ticket_active'
        );

        DB::statement('ALTER TABLE prescriptions ADD CONSTRAINT prescriptions_od_axis_range CHECK (od_axis IS NULL OR (od_axis BETWEEN 0 AND 180))');
        DB::statement('ALTER TABLE prescriptions ADD CONSTRAINT prescriptions_os_axis_range CHECK (os_axis IS NULL OR (os_axis BETWEEN 0 AND 180))');

        /**
         * Tiketni boshqa filialga o'tkazish tarixi (7.11) — sabab
         * majburiy, chunki bu qoidadan chekinish va u tushuntirilishi
         * kerak.
         */
        Schema::create('prescription_transfers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index('prescription_id');
        });

        // `orders.prescription_id` Bosqich 3 da tashqi kalitsiz qolgan
        // edi — `prescriptions` jadvali endi bor, bog'lash mumkin.
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['prescription_id']);
        });

        Schema::dropIfExists('prescription_transfers');
        Schema::dropIfExists('prescriptions');
    }
};
