<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yo'l varaqasi — SCHEMA.md, PROJECT.md §6.7, 7.4. BOSQICH-8.md.
 *
 * `trips` bitta haydovchi + bitta kun (`unique(driver_id, date)`) —
 * bir haydovchi bir kunda bitta reysga ega. `trip_stops` — marshrut
 * qatorlari, `sequence` bo'yicha tartiblangan.
 *
 * To'xtash ikki turda: `branch` (jo'natilgan transferni yetkazish) yoki
 * `customer` (buyurtmani yetkazish). Ikkalasida ham GPS tasdig'i bir xil
 * ustunlardan foydalanadi (7.4) — alohida jadval ochish keraksiz
 * dublikatsiya bo'lardi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 16);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->decimal('cash_collected', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['driver_id', 'date']);
        });

        Schema::create('trip_stops', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->smallInteger('sequence');
            $table->string('type', 16);

            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->restrictOnDelete();

            $table->string('address', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->decimal('cash_to_collect', 15, 2)->default(0);

            $table->string('status', 16);

            $table->timestampTz('delivered_at')->nullable();
            $table->decimal('delivered_lat', 10, 7)->nullable();
            $table->decimal('delivered_lng', 10, 7)->nullable();

            // Manzildan qancha farq qilgani (7.4) — ayblash uchun emas,
            // hujjat uchun. Chegara `SettingKey::DeliveryGpsToleranceMeters`.
            $table->integer('distance_m')->nullable();

            $table->string('photo_path', 255)->nullable();

            $table->timestampTz('customer_confirmed_at')->nullable();
            $table->string('confirmation_status', 16)->nullable();

            $table->string('fail_reason', 255)->nullable();

            $table->timestampsTz();

            $table->index(['trip_id', 'sequence']);
            $table->index(['confirmation_status']);
        });

        DB::statement(
            'ALTER TABLE trip_stops ADD CONSTRAINT trip_stops_target_chk CHECK ('
            ."(type = 'branch' AND branch_id IS NOT NULL) OR "
            ."(type = 'customer' AND customer_id IS NOT NULL)"
            .')'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trips');
    }
};
