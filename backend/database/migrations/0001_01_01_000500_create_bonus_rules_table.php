<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mukofot qoidasi — SCHEMA.md §9, PROJECT.md 7.19, BOSQICH-10.md §10b.
 *
 * `user_id` bo'lgan qoida rol qoidasidan **ustun** (7.19) —
 * `BonusRuleResolver` avval shaxsiy qoidani qidiradi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('base', 16);
            $table->decimal('percent', 5, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['user_id', 'valid_from']);
            $table->index(['role', 'branch_id', 'valid_from']);
        });

        DB::statement(
            'ALTER TABLE bonus_rules ADD CONSTRAINT bonus_rules_user_or_role '
            .'CHECK (user_id IS NOT NULL OR role IS NOT NULL)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_rules');
    }
};
