<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Catalog\Actions\Product\MergeProducts;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ConcurrentMergeReceiptTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function a_waiting_receipt_resolves_the_canonical_variant_after_the_merge_commits(): void
    {
        $branch = Branch::factory()->create();
        $location = Location::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $source = Product::factory()->create(['type' => 'lens', 'created_by' => $user->id]);
        $target = Product::factory()->create(['type' => 'lens', 'created_by' => $user->id]);
        $attrs = ['sph' => '-2.00', 'cyl' => '-0.50', 'axis' => 90, 'add' => '0.00', 'index' => '1.61', 'coating' => 'hmc'];
        $a = ProductVariant::factory()->create(['product_id' => $source->id, ...$attrs]);
        $b = ProductVariant::factory()->create(['product_id' => $target->id, ...$attrs]);
        $ledger = app(StockLedger::class);
        $ledger->receive($user, $location, $a->id, 2, Money::of('100'));
        DB::beginTransaction();
        $worker = null;
        try {
            DB::statement('SELECT pg_advisory_xact_lock(4230901)');
            Location::query()->whereKey($location->id)->lockForUpdate()->firstOrFail();
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/stock-receive-worker.php'), (string) $a->id, (string) $location->id, (string) $user->id], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => config('database.connections.pgsql.database'), 'DB_PORT' => config('database.connections.pgsql.port')]);
            $worker->start();
            $ready = $worker->waitUntil(fn (): bool => preg_match('/^\d+\r?\n/', $worker->getOutput()) === 1);
            $this->assertTrue($ready);
            $pid = (int) trim($worker->getOutput());
            $waiting = false;
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline) {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $row = DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [$pid]);
                if ($row?->wait_event_type === 'Lock') {
                    $waiting = true;
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($waiting, 'Receipt must reach the shared lock before merging.');
            app(MergeProducts::class)->handle($user, $source, $target);
            DB::commit();
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertSame(0, $ledger->balanceOf($a->id, $location->id));
            $this->assertSame(3, $ledger->balanceOf($b->id, $location->id));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker instanceof Process && $worker->isRunning()) {
                $worker->stop();
            }
        }
    }
}
