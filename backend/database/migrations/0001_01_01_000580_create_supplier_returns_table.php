<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('defect_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('draft');
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['defect_id', 'purchase_id']);
        });
        Schema::table('supplier_transactions', function (Blueprint $table): void {
            $table->foreignId('supplier_return_id')->nullable()->unique()->constrained('supplier_returns')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_return_id'));
        Schema::dropIfExists('supplier_returns');
    }
};
