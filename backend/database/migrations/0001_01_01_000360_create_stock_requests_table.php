<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ichki so'rov — SCHEMA.md §3, PROJECT.md §11 (Bosqich 4).
 *
 * Sotuvchi mijoz oldida turib "bu linza bizda yo'q, B filialda bor"
 * degan holatni hujjatga aylantiradi: so'rov yoziladi, tasdiqlanadi va
 * undan transfer tug'iladi. Shunda "nima uchun transfer qilindi" degan
 * savolga javob qoladi va yo'qotilgan savdo hisoboti to'g'ri chiqadi.
 *
 * `order_id` — so'rov konkret buyurtma uchun bo'lsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // So'ragan va so'ralayotgan filiallar — ikkalasi ham
            // so'rovni ko'rishi kerak.
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();

            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity');

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('status', 16);
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['to_branch_id', 'status']);
            $table->index(['from_branch_id', 'status']);
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
