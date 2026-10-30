<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->foreignId('merged_into_id')->nullable()->constrained('product_variants')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropConstrainedForeignId('merged_into_id'));
    }
};
