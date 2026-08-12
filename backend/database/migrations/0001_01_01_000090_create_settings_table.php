<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direktor o'zgartira oladigan sozlamalar — SCHEMA.md §1.
 *
 * `config/optika.php` standart qiymatni beradi, bu jadval uni ustidan
 * yopadi: `min_prepayment_percent` (7.7), `money_rounding_step` (§15 #19),
 * `prescription_validity_months` (7.11), `debt_reminder_days` (7.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key', 80)->unique();
            $table->jsonb('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
