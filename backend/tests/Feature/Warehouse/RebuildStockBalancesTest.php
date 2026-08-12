<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `stock:rebuild-balances` — PROJECT.md 7.1, SCHEMA.md §3.
 *
 * Kesh **faqat hosila**: u har doim daftardan qayta hisoblanishi
 * mumkin bo'lishi shart. SCHEMA.md bu testni majburiy deb belgilaydi.
 */
final class RebuildStockBalancesTest extends TestCase
{
    use RefreshDatabase;

    private StockLedger $ledger;

    private User $author;

    private Location $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StockLedger::class);

        $branch = Branch::factory()->main()->create();
        $this->author = User::factory()->for($branch)->create();
        $this->warehouse = Location::factory()->for($branch)->create(['type' => LocationType::Warehouse]);

        $product = Product::factory()->create(['created_by' => $this->author->id]);
        $this->variant = ProductVariant::factory()->for($product)->create();
    }

    #[Test]
    public function it_restores_a_corrupted_cache_from_the_ledger(): void
    {
        $this->ledger->receive($this->author, $this->warehouse, $this->variant->id, 10, Money::of('50000'));
        $this->ledger->issue($this->author, $this->warehouse, $this->variant->id, 3);

        // Keshni qo'lda buzamiz — qo'lda SQL aralashuvini taqlid qilish.
        DB::table('stock_balances')->update(['quantity' => 999]);

        $this->assertSame(0, $this->rebuild());

        $this->assertDatabaseHas('stock_balances', [
            'location_id' => $this->warehouse->id,
            'variant_id' => $this->variant->id,
            'quantity' => 7,
        ]);
    }

    #[Test]
    public function it_removes_cache_rows_the_ledger_does_not_know_about(): void
    {
        $other = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $this->author->id])
        )->create();

        DB::table('stock_balances')->insert([
            'location_id' => $this->warehouse->id,
            'variant_id' => $other->id,
            'branch_id' => $this->warehouse->branch_id,
            'quantity' => 42,
            'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->rebuild());

        $this->assertDatabaseMissing('stock_balances', ['variant_id' => $other->id]);
    }

    #[Test]
    public function a_dry_run_reports_differences_without_writing(): void
    {
        $this->ledger->receive($this->author, $this->warehouse, $this->variant->id, 10, Money::of('50000'));
        DB::table('stock_balances')->update(['quantity' => 999]);

        $this->assertSame(1, $this->rebuild('--dry-run'));

        // Yozmagan — kesh hali ham buzuq.
        $this->assertDatabaseHas('stock_balances', ['quantity' => 999]);
    }

    #[Test]
    public function a_dry_run_succeeds_when_the_cache_is_correct(): void
    {
        $this->ledger->receive($this->author, $this->warehouse, $this->variant->id, 10, Money::of('50000'));

        $this->assertSame(0, $this->rebuild('--dry-run'));
    }

    #[Test]
    public function the_rebuilt_cache_matches_the_ledger_across_many_movements(): void
    {
        $second = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $this->author->id])
        )->create();

        $this->ledger->receive($this->author, $this->warehouse, $this->variant->id, 10, Money::of('50000'));
        $this->ledger->receive($this->author, $this->warehouse, $second->id, 4, Money::of('70000'));
        $this->ledger->issue($this->author, $this->warehouse, $this->variant->id, 6);
        $this->ledger->issue($this->author, $this->warehouse, $second->id, 1);

        DB::table('stock_balances')->delete();
        $this->assertSame(0, $this->rebuild());

        $this->assertSame(
            4,
            (int) DB::table('stock_balances')
                ->where('variant_id', $this->variant->id)->value('quantity'),
        );
        $this->assertSame(
            3,
            (int) DB::table('stock_balances')
                ->where('variant_id', $second->id)->value('quantity'),
        );
    }

    /**
     * `Artisan::call()` chiqish kodini `int` qilib qaytaradi — tekshiruv
     * shu bo'yicha: 0 = mos, 1 = farq bor.
     */
    private function rebuild(string ...$options): int
    {
        return Artisan::call('stock:rebuild-balances '.implode(' ', $options));
    }
}
