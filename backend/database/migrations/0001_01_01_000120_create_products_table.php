<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tovarlar — SCHEMA.md §2, PROJECT.md 7.13, 7.17.
 *
 * `search_key` — hosila ustun (ANALIZ 3.14): nom -> kichik harf ->
 * kirilldan lotinga -> apostrof va tinish belgilarisiz. Ustiga GIN
 * trigram indeks qo'yiladi, shunda "Рэй бан" ham "Ray Ban" ni topadi.
 * Qiymat model `saving` hodisasida yoziladi, DB trigger emas.
 *
 * `quick_created` — sotuv paytida yaratilgan tovar (7.13); admin keyin
 * uni to'ldirib chiqadi (brend, kategoriya, shtrix-kod).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('type', 16);
            $table->string('name', 200);
            $table->string('search_key', 255);
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('unit', 16)->default('pcs');
            $table->string('status', 16)->default('pending');
            $table->boolean('quick_created')->default(false);
            $table->foreignId('merged_into_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE INDEX products_search_key_trgm ON products USING GIN (search_key gin_trgm_ops)');
        DB::statement("CREATE INDEX products_pending_idx ON products (status) WHERE status = 'pending'");
        DB::statement('CREATE INDEX products_type_active_idx ON products (type, is_active)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
