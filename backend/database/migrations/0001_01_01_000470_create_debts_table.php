<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qarz registri — SCHEMA.md, PROJECT.md 7.6, BOSQICH-10.md §10a.
 *
 * `debts` qo'lda ochilmaydi — `DebtRegistry` `orders.debt`/`due_date`
 * dan avtomatik sinxronlaydi (bir buyurtma — bir qarz qatori,
 * `order_id` shuning uchun `unique`).
 *
 * `debt_reminders` — CRM jurnali: Telegram avtomatik eslatmasi ham,
 * xodimning qo'lda qo'ng'irog'i ham shu yerga yoziladi
 * (BOSQICH-7.md §5 #2 bilan yarashish).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid', 15, 2)->default(0);
            $table->date('due_date');
            $table->string('status', 16);
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['customer_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('debt_reminders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->timestampTz('sent_at');
            $table->string('channel', 16);
            $table->string('response', 16)->default('none');
            $table->timestampTz('responded_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['debt_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_reminders');
        Schema::dropIfExists('debts');
    }
};
