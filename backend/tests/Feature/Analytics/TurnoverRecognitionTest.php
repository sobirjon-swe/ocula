<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\CashReportService;
use App\Modules\Analytics\Services\OperationalReportService;
use App\Modules\Analytics\Services\ProfitReportService;
use App\Modules\Catalog\Models\Price;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Shift;
use App\Modules\Sales\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class TurnoverRecognitionTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10);
    }

    #[Test]
    public function turnover_subtracts_partial_and_full_returns_in_their_reporting_period(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = (int) $this->postAction('/api/v1/checkout', [...$this->quickSalePayload(), 'payments' => [['amount' => '500000', 'method' => 'cash']]])->assertCreated()->json('data.id');
        $item = Order::findOrFail($id)->items()->firstOrFail();
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'refund' => '250000'])->assertCreated();
        $director = $this->actingAsDirector();
        $today = CarbonImmutable::today('Asia/Tashkent');
        $service = app(OperationalReportService::class);
        $rows = $service->generate('turnover', $director, $today, $today, $this->branch->id);
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['sold_quantity']);
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'refund' => '250000'])->assertCreated();
        $rows = $service->generate('turnover', $director, $today, $today, $this->branch->id);
        $this->assertSame(0, $rows[0]['sold_quantity']);
        $this->assertSame('0.0000', $rows[0]['turnover']);
        $this->assertNull($rows[0]['days_on_hand']);
    }

    #[Test]
    public function an_advance_remains_in_its_cash_shift_but_revenue_belongs_to_the_handover_shift(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        Price::where('variant_id', $this->variant->id)->update(['valid_from' => '2026-10-01']);
        try {
            $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00:00', 'Asia/Tashkent'));
            $old = Shift::factory()->for($this->branch)->create(['opened_at' => now()]);
            $id = (int) $this->postAction('/api/v1/orders', [...$this->quickSalePayload(), 'type' => 'order'])->assertCreated()->json('data.id');
            $this->postAction("/api/v1/orders/$id/payments", ['amount' => '500000', 'method' => 'cash'])->assertCreated();
            $old->update(['status' => 'closed', 'closed_at' => now()->addHours(10)]);
            $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'Asia/Tashkent'));
            $current = Shift::factory()->for($this->branch)->create(['opened_at' => now()]);
            Order::whereKey($id)->update(['status' => 'ready']);
            $this->postAction("/api/v1/orders/$id/deliver")->assertOk();
            $director = $this->actingAsDirector();
            $today = CarbonImmutable::parse('2026-10-30', 'Asia/Tashkent');
            $yesterday = $today->subDay();
            $profit = app(ProfitReportService::class);
            $this->assertSame('500000.00', $profit->generate($director, $today, $today)['revenue']);
            $this->assertSame('0.00', $profit->generate($director, $yesterday, $yesterday)['revenue']);
            $this->assertSame($current->id, Order::findOrFail($id)->shift_id);
            $this->assertSame('500000.00', app(CashReportService::class)->generate($director, $yesterday, $yesterday)['net_total']);
            $this->assertDatabaseHas('payments', ['order_id' => $id, 'shift_id' => $old->id]);
        } finally {
            $this->travelBack();
        }
    }
}
