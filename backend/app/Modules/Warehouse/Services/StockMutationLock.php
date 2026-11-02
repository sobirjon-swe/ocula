<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Services;

use Illuminate\Support\Facades\DB;

/** A transaction-wide lock covers merge identity, FIFO layers, counts and balances together. */
final class StockMutationLock
{
    public static function acquire(): void
    {
        DB::statement('SELECT pg_advisory_xact_lock(4230901)');
    }
}
