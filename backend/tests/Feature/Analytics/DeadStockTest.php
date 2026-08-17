<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\DeadStockReportService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * O'lik zaxira — PROJECT.md 6.11.
 *
 * Asosiy kafolat: 90+ kun sotilmagan (yoki hech sotilmagan) qoldiq
 * topiladi, yaqinda sotilgan chiqmaydi.
 */
final class DeadStockTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private Location $warehouse;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->create(['code' => 'A']);
        $this->warehouse = Location::factory()->for($this->branch)->create(['type' => LocationType::Warehouse]);
        $this->director = $this->employee(Role::Director);
    }

    #[Test]
    public function stock_untouched_for_90_days_is_reported_dead(): void
    {
        $ledger = app(StockLedger::class);

        $staleVariant = $this->variant();
        $ledger->receive($this->director, $this->warehouse, $staleVariant->id, 5, Money::of('10000'));
        $saleMovement = $ledger->issue(
            $this->director, $this->warehouse, $staleVariant->id, 1, MovementType::Sale,
        );
        DB::table('stock_movements')->where('id', $saleMovement->id)
            ->update(['created_at' => now()->subDays(120)]);

        $freshVariant = $this->variant();
        $ledger->receive($this->director, $this->warehouse, $freshVariant->id, 5, Money::of('10000'));
        $ledger->issue($this->director, $this->warehouse, $freshVariant->id, 1, MovementType::Sale);

        $neverSoldVariant = $this->variant();
        $ledger->receive($this->director, $this->warehouse, $neverSoldVariant->id, 5, Money::of('10000'));

        $result = app(DeadStockReportService::class)->generate($this->director, null, 90);
        $deadVariantIds = collect($result)->pluck('variant_id')->all();

        $this->assertContains($staleVariant->id, $deadVariantIds);
        $this->assertContains($neverSoldVariant->id, $deadVariantIds);
        $this->assertNotContains($freshVariant->id, $deadVariantIds);
    }

    private function variant(): ProductVariant
    {
        return ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $this->director->id])
        )->create();
    }
}
