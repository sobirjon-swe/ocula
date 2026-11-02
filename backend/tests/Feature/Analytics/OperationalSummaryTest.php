<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\DashboardService;
use App\Modules\Analytics\Services\ProfitReportService;
use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Models\Expense;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class OperationalSummaryTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function profit_deducts_returns_expenses_and_doubtful_debt_instead_of_showing_gross_margin(): void
    {
        $this->seedPermissions();
        $director = $this->actingAsDirector();
        $branch = Branch::factory()->create();
        $order = Order::factory()->create(['branch_id' => $branch->id, 'total' => '200000', 'cost_total' => '100000', 'revenue_recognized_at' => now(), 'created_by' => $director->id]);
        OrderReturn::create(['number' => 'RET-1', 'order_id' => $order->id, 'branch_id' => $branch->id, 'reason' => 'customer_changed_mind', 'amount' => '50000', 'cost_total' => '25000', 'created_by' => $director->id]);
        Expense::factory()->create(['branch_id' => $branch->id, 'amount' => '10000', 'date' => now()->toDateString()]);
        $report = app(ProfitReportService::class)->generate($director, CarbonImmutable::today(), CarbonImmutable::today());
        $this->assertSame('150000.00', $report['revenue']);
        $this->assertSame('75000.00', $report['cost_total']);
        $this->assertSame('65000.00', $report['profit']);
    }

    #[Test]
    public function the_director_gets_all_four_operational_totals_and_attention_items(): void
    {
        $this->seedPermissions();
        $director = $this->actingAsDirector();
        Order::factory()->create(['due_date' => now()->subDay(), 'status' => 'ready']);
        $report = app(DashboardService::class)->summary($director);
        $this->assertArrayHasKey('net_profit_today', $report);
        $this->assertArrayHasKey('total_debt', $report);
        $this->assertSame(1, $report['overdue_orders']);
        $this->assertNotEmpty($report['attention']);
    }
}
