<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL kengaytmalari — SCHEMA.md §11.
 *
 * `pg_trgm`  — fuzzy qidiruv: "Рэй бан" ham "Ray Ban" ni topsin (7.13, 3.14).
 * `unaccent` — diakritikani olib tashlash.
 *
 * Ikkalasi ham `products.search_key` GIN indeksida ishlatiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(): void
    {
        // Kengaytmalar o'chirilmaydi — boshqa sxemalar ham ishlatayotgan bo'lishi mumkin.
    }
};
