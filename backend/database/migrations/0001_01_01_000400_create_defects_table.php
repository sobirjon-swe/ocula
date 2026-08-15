<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brak — SCHEMA.md §3, PROJECT.md 7.5.
 *
 * "Usta faqat sababni tanlaydi, qolganini tizim qiladi": sabab kim
 * to'lashini belgilaydi va keyingi harakatni tanlaydi —
 * `customer_request` da buyurtma qayta ishlashga qaytadi,
 * `master_error` usta statistikasiga tushadi va hokazo.
 *
 * `cost_impact` — yo'qotilgan qiymat, FIFO dan (7.20). Bu raqamsiz
 * "brak qancha turdi" degan savolga javob bo'lmasdi va sabablar
 * bo'yicha taqqoslash ma'nosini yo'qotardi.
 *
 * Manba ustunlari (`order_id`, `work_order_id`, `transfer_id`) —
 * brak qayerda chiqqanini ko'rsatadi va uchtasi ham ixtiyoriy: brak
 * shunchaki omborda ham topilishi mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity');

            $table->string('reason', 24);

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();

            $table->decimal('cost_impact', 15, 2)->default(0);

            // Tovar omborda bo'lgan bo'lsa — uni hisobdan chiqargan
            // harakat. Mijozdan qaytgan brakda ombor tegilmaydi, shuning
            // uchun ustun `null` bo'lishi mumkin.
            $table->foreignId('movement_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();

            $table->string('photo_path', 255)->nullable();
            $table->text('note')->nullable();

            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'reason', 'created_at']);
            $table->index('reported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
