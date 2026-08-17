<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\AbcAnalysisService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * ABC tahlil — PROJECT.md 6.11.
 *
 * Asosiy kafolat: daromad bo'yicha kamayish tartibida, kumulyativ %
 * dan A/B/C to'g'ri chiqadi.
 */
final class AbcAnalysisTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->create(['code' => 'A']);
        $this->director = $this->employee(Role::Director);
    }

    #[Test]
    public function products_are_classified_by_cumulative_revenue_share(): void
    {
        $variantA = $this->variant();
        $variantB = $this->variant();
        $variantC = $this->variant();

        $this->soldOrder($variantA, '800000');
        $this->soldOrder($variantB, '150000');
        $this->soldOrder($variantC, '50000');

        $result = app(AbcAnalysisService::class)->generate(
            $this->director,
            CarbonImmutable::today()->startOfMonth(),
            CarbonImmutable::today(),
        );

        $tiers = collect($result)->keyBy('variant_id');

        $this->assertSame('A', $tiers[$variantA->id]['tier']);
        $this->assertSame('B', $tiers[$variantB->id]['tier']);
        $this->assertSame('C', $tiers[$variantC->id]['tier']);
    }

    private function variant(): ProductVariant
    {
        return ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $this->director->id])
        )->create();
    }

    private function soldOrder(ProductVariant $variant, string $revenue): Order
    {
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->director->id,
            'total' => $revenue,
            'revenue_recognized_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'itemable_type' => ProductVariant::class,
            'itemable_id' => $variant->id,
            'quantity' => 1,
            'price' => $revenue,
            'total' => $revenue,
        ]);

        return $order;
    }
}
