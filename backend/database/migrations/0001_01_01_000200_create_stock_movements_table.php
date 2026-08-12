<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ombor harakatlari daftari — SCHEMA.md §3, PROJECT.md 7.1.
 *
 * Qoldiq hech qachon jadvalda raqam bo'lib turmaydi: u shu yozuvlar
 * yig'indisi. `stock_balances` faqat kesh.
 *
 * **Insert-only** (7.21): `updated_at` yo'q, `deleted_at` yo'q. Xato
 * bo'lsa storno — teskari ishorali yangi yozuv + `reverses_id` + `reason`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->bigIncrements('id');

            // Denormalizatsiya (ANALIZ 3.10): `BranchScope` har so'rovda
            // `locations` ga join qilmasligi uchun. Transit harakatida
            // jo'natuvchi filial yoziladi.
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->string('type', 20);
            $table->integer('quantity');
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->boolean('cost_incomplete')->default(false);

            $table->string('source_type', 64)->nullable();
            $table->bigInteger('source_id')->nullable();

            $table->foreignId('reverses_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();
            $table->string('reason', 255)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
        });

        DB::statement(
            'CREATE INDEX sm_variant_location_idx
               ON stock_movements (variant_id, location_id, created_at)'
        );
        DB::statement('CREATE INDEX sm_branch_created_idx ON stock_movements (branch_id, created_at)');
        DB::statement('CREATE INDEX sm_source_idx ON stock_movements (source_type, source_id)');
        DB::statement('CREATE INDEX sm_type_idx ON stock_movements (type, created_at)');

        // Nol miqdorli harakat ma'nosiz — u qoldiqni ham, tannarxni ham
        // o'zgartirmaydi, lekin hisobotda shovqin bo'lib qoladi.
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT sm_quantity_not_zero CHECK (quantity <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
