<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qoldiq joylari — SCHEMA.md §1, PROJECT.md 7.1.
 *
 * `owner` polimorf (ANALIZ 3.2): `transit` egasi haydovchi (`User`)
 * yoki transferning o'zi (`Transfer`) bo'ladi — 7.10.
 *
 * 1-versiyada har filialda bitta `warehouse` (§15 #22) — partial unique
 * indeks buni majburlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('name', 80);
            $table->string('owner_type', 64)->nullable();
            $table->bigInteger('owner_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['branch_id', 'type']);
            $table->index(['owner_type', 'owner_id']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX locations_branch_singleton
               ON locations (branch_id, type)
               WHERE type IN ('warehouse', 'floor')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
