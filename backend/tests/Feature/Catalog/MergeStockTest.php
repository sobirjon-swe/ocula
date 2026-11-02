<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Actions\Product\MergeProducts;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class MergeStockTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    #[Test]
    public function a_merged_variants_stock_remains_sellable_under_the_target_without_rewriting_history(): void
    {
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->actingAsDirector();
        $this->stockUp(3);
        $source = $this->variant->product;
        $target = Product::factory()->create(['type' => $source->type, 'created_by' => $this->author->id]);
        app(MergeProducts::class)->handle($this->author, $source, $target);
        $this->assertSame($target->id, $this->variant->fresh()->product_id);
        $this->assertSame(3, $this->balance());
        $this->getJson('/api/v1/catalog-variants?search='.$this->variant->sku)->assertOk()->assertJsonPath('data.0.id', $this->variant->id);
    }

    #[Test]
    public function matching_lens_variants_merge_balances_through_fifo_movements(): void
    {
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->actingAsDirector();
        $source = Product::factory()->create(['type' => 'lens', 'created_by' => $this->author->id]);
        $target = Product::factory()->create(['type' => 'lens', 'created_by' => $this->author->id]);
        $a = ProductVariant::factory()->create(['product_id' => $source->id, 'sph' => '-2.00', 'cyl' => '-0.50', 'axis' => 90, 'add' => '0.00', 'index' => '1.61', 'coating' => 'hmc']);
        $b = ProductVariant::factory()->create(['product_id' => $target->id, 'sph' => '-2.00', 'cyl' => '-0.50', 'axis' => 90, 'add' => '0.00', 'index' => '1.61', 'coating' => 'hmc']);
        $ledger = app(StockLedger::class);
        $original = $ledger->receive($this->author, $this->warehouse, $a->id, 3, Money::of('10000'));
        app(MergeProducts::class)->handle($this->author, $source, $target);
        $this->assertSame(0, $ledger->balanceOf($a->id, $this->warehouse->id));
        $this->assertSame(3, $ledger->balanceOf($b->id, $this->warehouse->id));
        $this->assertSame($b->id, $a->fresh()->merged_into_id);
        $this->assertSame(3, StockMovement::findOrFail($original->id)->quantity);
        // Existing orders/transfers can still reference the old identity after merge.
        $received = $ledger->receive($this->author, $this->warehouse, $a->id, 1, Money::of('10000'));
        $this->assertSame($b->id, $received->variant_id);
        $ledger->issue($this->author, $this->warehouse, $a->id, 1);
        $this->assertSame(0, $ledger->balanceOf($a->id, $this->warehouse->id));
        $this->assertSame(3, $ledger->balanceOf($b->id, $this->warehouse->id));
    }

    #[Test]
    public function merging_preserves_individual_fifo_costs_instead_of_averaging(): void
    {
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->actingAsDirector();
        $source = Product::factory()->create(['type' => 'lens', 'created_by' => $this->author->id]);
        $target = Product::factory()->create(['type' => 'lens', 'created_by' => $this->author->id]);
        $attrs = ['sph' => '-2.00', 'cyl' => '-0.50', 'axis' => 90, 'add' => '0.00', 'index' => '1.61', 'coating' => 'hmc'];
        $a = ProductVariant::factory()->create(['product_id' => $source->id, ...$attrs]);
        $b = ProductVariant::factory()->create(['product_id' => $target->id, ...$attrs]);
        $ledger = app(StockLedger::class);
        $ledger->receive($this->author, $this->warehouse, $a->id, 1, Money::of('100'));
        $ledger->receive($this->author, $this->warehouse, $a->id, 1, Money::of('300'));
        app(MergeProducts::class)->handle($this->author, $source, $target);
        $sale = $ledger->issue($this->author, $this->warehouse, $b->id, 1);
        $this->assertSame('100.00', $sale->cost_total->toString());
    }
}
