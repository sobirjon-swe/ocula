<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('debt_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('debt_approved_at')->nullable();
            $table->text('debt_approval_reason')->nullable();
            $table->decimal('debt_approved_amount', 15, 2)->nullable();
        });
        Schema::table('bonus_rules', function (Blueprint $table): void {
            $table->decimal('rate', 15, 2)->nullable();
            $table->string('product_type', 30)->nullable();
        });
        Schema::table('bonus_entries', function (Blueprint $table): void {
            $table->foreignId('defect_id')->nullable()->unique()->constrained('defects')->restrictOnDelete();
            $table->jsonb('basis_items')->nullable();
            $table->string('adjustment_kind', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bonus_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('defect_id');
            $table->dropColumn(['basis_items', 'adjustment_kind']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('debt_approved_by');
            $table->dropColumn(['debt_approved_at', 'debt_approval_reason', 'debt_approved_amount']);
        });
        Schema::table('bonus_rules', fn (Blueprint $table) => $table->dropColumn(['rate', 'product_type']));
    }
};
