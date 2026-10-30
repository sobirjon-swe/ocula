<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'services', 'expense_categories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->jsonb('name_translations')->nullable());
        }
        foreach ([
            "Shifokor ko'rigi" => ['ru' => 'Осмотр врача', 'en' => 'Eye examination'],
            "Linza o'rnatish" => ['ru' => 'Установка линз', 'en' => 'Lens fitting'],
            "Ko'zoynak ta'miri" => ['ru' => 'Ремонт очков', 'en' => 'Glasses repair'],
            'Sozlash' => ['ru' => 'Регулировка', 'en' => 'Adjustment'],
        ] as $name => $translations) {
            DB::table('services')->where('name', $name)->update(['name_translations' => json_encode(['uz-latn' => $name, ...$translations], JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        foreach (['categories', 'services', 'expense_categories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('name_translations'));
        }
    }
};
