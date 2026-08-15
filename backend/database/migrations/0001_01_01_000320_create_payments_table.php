<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * To'lovlar — SCHEMA.md §4, PROJECT.md 7.21.
 *
 * **Insert-only**: `updated_at` yo'q, `DELETE` yo'q. Xato bo'lsa storno —
 * manfiy summali yangi yozuv `reverses_id` bilan aslga bog'lanadi.
 *
 * Muvaffaqiyatsiz to'lov umuman yozilmaydi (ENUMS.md §4), shuning uchun
 * `failed` holati yo'q.
 *
 * `amount` ishorali: qaytarishda manfiy. Buyurtmaning to'langan summasi
 * shu yozuvlar yig'indisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // Buyurtmasiz to'lov ham bo'ladi: mijoz eski qarzini yopgani
            // kelsa, u konkret chekka bog'lanmasligi mumkin (7.6).
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('method', 16);
            $table->string('status', 16);

            $table->foreignId('reverses_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('reason', 255)->nullable();

            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();

            // Haydovchi yig'gan pul (7.4): `collected_by` bor, lekin
            // kassaga tushmagan bo'lsa `status = pending`.
            $table->foreignId('collected_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestampTz('paid_at');
            $table->timestampTz('created_at');

            $table->index('order_id');
            $table->index(['customer_id', 'paid_at']);
            $table->index('shift_id');
            $table->index(['branch_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
