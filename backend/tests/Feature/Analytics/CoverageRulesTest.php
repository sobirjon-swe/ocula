<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\AbcAnalysisService;
use App\Modules\Analytics\Services\BranchRankingService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Payroll\Models\BranchPlan;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class CoverageRulesTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    public function test_abc_separates_pending_and_resolves_merged_variant_history(): void
    {
        $this->seedPermissions();
        $director = $this->employee(Role::Director);
        $branch = Branch::factory()->create();
        $canonical = ProductVariant::factory()->create();
        $other = ProductVariant::factory()->create();
        $duplicate = Product::factory()->create(['status' => 'rejected', 'merged_into_id' => $canonical->product_id]);
        $old = ProductVariant::factory()->create(['product_id' => $duplicate->id, 'merged_into_id' => $canonical->id]);
        $pending = ProductVariant::factory()->create(['product_id' => Product::factory()->quickCreated()->create()->id]);
        foreach ([[$canonical, '700'], [$old, '100'], [$other, '200'], [$pending, '1000']] as [$variant, $revenue]) {
            $order = Order::factory()->create(['branch_id' => $branch->id, 'total' => $revenue, 'revenue_recognized_at' => now()]);
            OrderItem::create(['order_id' => $order->id, 'itemable_type' => ProductVariant::class, 'itemable_id' => $variant->id, 'quantity' => 1, 'price' => $revenue, 'total' => $revenue]);
        }
        $rows = collect(app(AbcAnalysisService::class)->generate($director, CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()))->keyBy('variant_id');
        $this->assertSame('800.00', $rows[$canonical->id]['revenue']);
        $this->assertSame($canonical->product_id, $rows[$canonical->id]['product_id']);
        $this->assertSame('80.00', $rows[$canonical->id]['cumulative_percent']);
        $this->assertSame('unchecked', $rows[$pending->id]['tier']);
        $this->assertNull($rows[$pending->id]['cumulative_percent']);
        $this->assertFalse($rows->has($old->id));
    }

    public function test_abc_and_monthly_ranking_follow_shift_opening_business_date(): void
    {
        $this->seedPermissions();
        $director = $this->employee(Role::Director);
        $branch = Branch::factory()->create();
        $variant = ProductVariant::factory()->create();
        $shift = Shift::factory()->create(['branch_id' => $branch->id, 'opened_at' => '2026-10-31 16:00:00+00']);
        $order = Order::factory()->create(['branch_id' => $branch->id, 'shift_id' => $shift->id, 'total' => '500', 'revenue_recognized_at' => '2026-10-31 19:30:00+00']);
        OrderItem::create(['order_id' => $order->id, 'itemable_type' => ProductVariant::class, 'itemable_id' => $variant->id, 'quantity' => 1, 'price' => '500', 'total' => '500']);
        BranchPlan::factory()->create(['branch_id' => $branch->id, 'period' => '2026-10', 'target_amount' => '1000', 'type' => 'revenue', 'created_by' => $director->id]);
        $date = CarbonImmutable::parse('2026-10-31', 'Asia/Tashkent');
        $abc = app(AbcAnalysisService::class)->generate($director, $date, $date);
        $this->assertSame('500.00', $abc[0]['revenue']);
        $ranking = app(BranchRankingService::class)->generate($director, '2026-10');
        $this->assertSame('50.00', $ranking[0]['percent']);
        $this->assertArrayHasKey('cash_today', $ranking[0]);
        $this->assertArrayHasKey('month_elapsed_pct', $ranking[0]);
    }
}
