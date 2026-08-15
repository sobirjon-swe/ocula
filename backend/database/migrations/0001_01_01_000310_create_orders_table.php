<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyurtma va uning satrlari — SCHEMA.md §4, PROJECT.md 7.3, 7.8.
 *
 * Ikkita **mustaqil** o'q: `status` (bajarilish) va `payment_status`
 * (to'lov). `ready` + `partial` — normal holat, ularni aralashtirmaslik
 * kerak (7.3).
 *
 * `revenue_recognized_at` — daromad sanasi (7.8). Foyda hisoboti shu
 * ustun bo'yicha ketadi, `created_at` bo'yicha emas: kechagi buyurtma
 * bugun topshirilsa, daromad bugungi kunga tushadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('number', 32)->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Tez savdoda mijoz ko'rsatilmasligi mumkin — chek anonim.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();

            $table->string('type', 8);
            $table->string('status', 24);
            $table->string('payment_status', 16);

            // `prescriptions` jadvali Clinic bosqichida keladi — ustun
            // hozircha tashqi kalitsiz turadi (SCHEMA.md §12).
            $table->unsignedBigInteger('prescription_id')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('rounding', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('debt', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);

            $table->date('due_date')->nullable();
            $table->string('delivery_type', 16)->default('pickup');

            $table->timestampTz('status_changed_at')->nullable();
            $table->timestampTz('revenue_recognized_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('abandoned_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        DB::statement('CREATE INDEX orders_branch_status_idx ON orders (branch_id, status)');

        // "Bajarilmagan buyurtmalar majburiyati" (7.8) shu indeks bilan
        // hisoblanadi — alohida jadval kerak emas.
        DB::statement('CREATE INDEX orders_obligation_idx ON orders (status, payment_status)');
        DB::statement('CREATE INDEX orders_revenue_idx ON orders (revenue_recognized_at)');
        DB::statement('CREATE INDEX orders_customer_idx ON orders (customer_id, created_at)');
        DB::statement('CREATE INDEX orders_shift_idx ON orders (shift_id)');

        Schema::create('order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // Polimorf: `ProductVariant` yoki `Service` (SCHEMA.md §4).
            $table->string('itemable_type', 64);
            $table->unsignedBigInteger('itemable_id');

            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);

            // Tannarx topshirish paytida FIFO dan keladi (7.20). Xizmatda
            // tannarx yo'q, individual linzada u qo'lda kiritiladi (3.9) —
            // shuni `cost_source` ajratib turadi, aks holda hisobotda
            // tannarxsiz sotuv foydani soxta yuqori ko'rsatardi.
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->string('cost_source', 16)->default('fifo');
            $table->foreignId('purchase_item_id')->nullable()
                ->constrained('purchase_items')->nullOnDelete();

            $table->jsonb('custom_lens_params')->nullable();
            $table->foreignId('movement_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();

            $table->timestampTz('created_at')->nullable();

            $table->index('order_id');
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
