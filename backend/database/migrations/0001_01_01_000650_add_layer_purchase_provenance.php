<?php

declare(strict_types=1);

use App\Modules\Warehouse\Models\Purchase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_layers', fn (Blueprint $table) => $table->foreignId('origin_purchase_id')->nullable()->constrained('purchases')->restrictOnDelete());
        DB::statement('UPDATE stock_layers SET origin_purchase_id = sm.source_id FROM stock_movements sm WHERE sm.id = stock_layers.movement_id AND sm.source_type = ?', [Purchase::class]);
    }

    public function down(): void
    {
        Schema::table('stock_layers', fn (Blueprint $table) => $table->dropConstrainedForeignId('origin_purchase_id'));
    }
};
