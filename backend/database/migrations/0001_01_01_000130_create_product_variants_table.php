<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tovar variantlari — SCHEMA.md §2, PROJECT.md 7.2.
 *
 * Har bir diopter kombinatsiyasi alohida **tovar** emas, alohida
 * **variant**. Ombordagi faqat haqiqatan turadigan kombinatsiyalar
 * yaratiladi (lazy creation — birinchi kirimda paydo bo'ladi),
 * aks holda 20 000+ keraksiz yozuv chiqadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 64)->nullable()->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->jsonb('attributes')->default('{}');
            $table->decimal('sph', 4, 2)->nullable();
            $table->decimal('cyl', 4, 2)->nullable();
            $table->smallInteger('axis')->nullable();
            $table->decimal('add', 4, 2)->nullable();
            $table->decimal('index', 3, 2)->nullable();
            $table->string('coating', 16)->nullable();
            $table->smallInteger('diameter')->nullable();
            $table->string('color', 40)->nullable();
            $table->string('size', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('product_id');
        });

        DB::statement('CREATE INDEX variants_attrs_gin ON product_variants USING GIN (attributes)');

        // Linza kombinatsiyasi takrorlanmasin (7.2). `add` va `index` —
        // Postgres kalit so'zlari, shuning uchun qo'shtirnoq ichida.
        DB::statement(
            'CREATE UNIQUE INDEX variants_lens_combo ON product_variants
               (product_id, sph, cyl, axis, "add", "index", coating)
               WHERE sph IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
