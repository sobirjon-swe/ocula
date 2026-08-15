<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filiallararo transfer — SCHEMA.md §3, PROJECT.md 7.10, ANALIZ 3.2.
 *
 * Transfer **ikki bosqichli**: jo'natilganda tovar jo'natuvchi filialdan
 * chiqib "yo'lda" (`transit` location) turadi, qabul qilinganda esa
 * yangi filialga kiradi. Oradagi vaqtda qoldiq hech qayerda yo'qolmaydi
 * — u transitda ko'rinib turadi.
 *
 * Filial ustuni **yo'q**: transferning ikkita tomoni bor (`from`/`to`),
 * shuning uchun ko'rinish cheklovi ikkala location bo'yicha hisoblanadi
 * (`TransferVisibilityScope`), oddiy `BranchScope` bilan emas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('number', 32)->unique();

            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();

            // "Yo'lda" turgan joy — jo'natish paytida yaratiladi (3.2).
            $table->foreignId('transit_location_id')->nullable()
                ->constrained('locations')->restrictOnDelete();

            $table->string('status', 24);
            $table->string('delivery_method', 16);

            $table->foreignId('driver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('carrier_user_id')->nullable()->constrained('users')->restrictOnDelete();

            // Taksida majburiy (7.10) — tekshiruv ilova darajasida.
            $table->decimal('taxi_cost', 15, 2)->nullable();
            $table->string('taxi_receipt_path', 255)->nullable();

            // `trips` jadvali Bosqich 8 da keladi — ustun hozircha
            // tashqi kalitsiz turadi (SCHEMA.md §12 tartibi).
            $table->unsignedBigInteger('trip_id')->nullable();

            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('received_at')->nullable();

            // Jo'natilganidan kam kelgan bo'lsa — direktorga signal (7.4).
            $table->boolean('has_discrepancy')->default(false);

            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index('status');
            $table->index(['from_location_id', 'status']);
            $table->index(['to_location_id', 'status']);
            $table->index('trip_id');
        });

        Schema::create('transfer_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->integer('qty_sent');

            // Qabul qilinmaguncha `null` — "hali sanalmagan" bilan
            // "nol dona keldi" bir xil narsa emas.
            $table->integer('qty_received')->nullable();

            // Jo'natish paytida FIFO dan hisoblanadi va butun yo'l
            // davomida o'zgarmaydi (SCHEMA.md §3).
            $table->decimal('unit_cost', 15, 2)->nullable();

            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
    }
};
