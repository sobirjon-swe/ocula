<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\CashReportService;
use App\Modules\Analytics\Services\ProfitReportService;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Kassa (7.8-A) va savdo/foyda (7.8-B) hisobotlari — ular hech qachon
 * teng bo'lmaydi (7.8).
 */
final class CashProfitReportTest extends TestCase
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
    public function the_cash_report_sums_movements_by_category(): void
    {
        $register = app(CashRegister::class);
        $register->record($this->director, $this->branch->id, CashCategory::Collection, Money::of('300000'));
        $register->record($this->director, $this->branch->id, CashCategory::Expense, Money::of('50000'));

        $today = CarbonImmutable::today();
        $report = app(CashReportService::class)->generate($this->director, $today, $today);

        $this->assertSame('250000.00', $report['net_total']);
    }

    #[Test]
    public function the_profit_report_uses_revenue_recognized_orders_only(): void
    {
        Order::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->director->id,
            'total' => '200000',
            'cost_total' => '120000',
            'revenue_recognized_at' => now(),
        ]);

        // Hali topshirilmagan — daromadga kirmaydi.
        Order::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->director->id,
            'total' => '500000',
            'revenue_recognized_at' => null,
        ]);

        $today = CarbonImmutable::today();
        $report = app(ProfitReportService::class)->generate($this->director, $today->startOfMonth(), $today);

        $this->assertSame(1, $report['orders_count']);
        $this->assertSame('200000.00', $report['revenue']);
        $this->assertSame('80000.00', $report['profit']);
    }

    #[Test]
    public function the_outstanding_liability_is_the_cash_collected_on_undelivered_orders(): void
    {
        Order::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->director->id,
            'status' => OrderStatus::New,
            'paid' => '150000',
        ]);

        $liability = app(ProfitReportService::class)->outstandingLiability($this->director);

        $this->assertSame('150000.00', $liability->toString());
    }
}
