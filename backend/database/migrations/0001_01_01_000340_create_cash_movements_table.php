<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kassa daftari — SCHEMA.md §8, PROJECT.md 7.21.
 *
 * `stock_movements` ning ko'zgusi: seyfdagi pul hech qachon jadvalda
 * raqam bo'lib turmaydi, u shu yozuvlar yig'indisi.
 *
 * **Insert-only**: `updated_at` yo'q, `DELETE` yo'q, tuzatish faqat
 * storno (`reverses_id` + `reason`).
 *
 * `amount` **har doim musbat** — yo'nalishni `type` (`in`/`out`)
 * belgilaydi. Bu ataylab: manfiy `in` yozuvi hisobotda "kirim" qatorida
 * turib, summani kamaytirib yuborardi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();

            $table->string('type', 4);
            $table->string('category', 24);
            $table->decimal('amount', 15, 2);

            $table->string('source_type', 64)->nullable();
            $table->bigInteger('source_id')->nullable();

            $table->foreignId('reverses_id')->nullable()
                ->constrained('cash_movements')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->string('description', 255)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');

            $table->index(['branch_id', 'created_at']);
            $table->index('shift_id');
            $table->index(['category', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        // Nol summali harakat seyfdagi pulni o'zgartirmaydi, lekin
        // kassa daftarida shovqin bo'lib qoladi.
        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT cm_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
