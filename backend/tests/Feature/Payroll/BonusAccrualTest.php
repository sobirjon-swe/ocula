<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Payroll\Services\BonusAccrual;
use App\Modules\Sales\Actions\Order\DeliverOrder;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Services\OrderBalance;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Mukofot hisoblash — PROJECT.md 7.12.
 *
 * Asosiy kafolatlar: buyurtma yopilganda sotuvchi/shifokor/usta uchun
 * yoziladi; ikki marta yozilmaydi; qaytarishda storno bo'ladi.
 */
final class BonusAccrualTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->create(['code' => 'A']);
        $this->author = User::factory()->create();
    }

    #[Test]
    public function a_seller_earns_a_revenue_bonus_when_the_order_closes(): void
    {
        $seller = User::factory()->for($this->branch)->create();
        $seller->assignRole(Role::Seller->value);
        $this->bonusRule(role: Role::Seller, base: BonusBase::Revenue, percent: '5.00');

        $order = $this->deliveredAndFullyPaidOrder($seller);

        app(OrderBalance::class)->refresh($order);

        $this->assertSame(OrderStatus::Closed, $order->fresh()->status);

        $entry = BonusEntry::query()->where('user_id', $seller->id)->firstOrFail();
        $this->assertSame('200000.00', $entry->base_amount->toString());
        $this->assertSame('10000.00', $entry->amount->toString());
        $this->assertSame(BonusStatus::Accrued, $entry->status);
    }

    #[Test]
    public function accruing_the_same_order_twice_does_not_duplicate(): void
    {
        $seller = User::factory()->for($this->branch)->create();
        $seller->assignRole(Role::Seller->value);
        $this->bonusRule(role: Role::Seller, base: BonusBase::Revenue, percent: '5.00');

        $order = $this->deliveredAndFullyPaidOrder($seller);

        $accrual = app(BonusAccrual::class);
        $accrual->accrueForOrder($order->fresh());
        $accrual->accrueForOrder($order->fresh());

        $this->assertSame(1, BonusEntry::query()->where('user_id', $seller->id)->count());
    }

    #[Test]
    public function a_doctor_earns_a_profit_bonus_via_the_prescription(): void
    {
        $doctor = User::factory()->for($this->branch)->create();
        $doctor->assignRole(Role::Doctor->value);
        $this->bonusRule(role: Role::Doctor, base: BonusBase::Profit, percent: '10.00');

        $customer = Customer::factory()->create();
        $prescription = Prescription::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'doctor_id' => $doctor->id,
        ]);

        $order = $this->deliveredAndFullyPaidOrder($this->author, [
            'customer_id' => $customer->id,
            'prescription_id' => $prescription->id,
            'total' => '200000',
            'cost_total' => '120000',
        ]);

        app(OrderBalance::class)->refresh($order);

        $entry = BonusEntry::query()->where('user_id', $doctor->id)->firstOrFail();
        // Foyda = 200000 - 120000 = 80000; 10% = 8000.
        $this->assertSame('80000.00', $entry->base_amount->toString());
        $this->assertSame('8000.00', $entry->amount->toString());
    }

    #[Test]
    public function a_master_earns_a_count_bonus_net_of_their_own_defects(): void
    {
        $master = User::factory()->for($this->branch)->create();
        $master->assignRole(Role::Master->value);
        $this->bonusRule(role: Role::Master, base: BonusBase::Count, percent: '50.00');

        $order = $this->deliveredAndFullyPaidOrder($this->author);

        $workOrder = WorkOrder::factory()->create([
            'order_id' => $order->id,
            'branch_id' => $this->branch->id,
            'master_id' => $master->id,
            'status' => WorkOrderStatus::Done->value,
            'finished_at' => now(),
            'created_by' => $this->author->id,
        ]);

        Defect::factory()->create([
            'branch_id' => $this->branch->id,
            'work_order_id' => $workOrder->id,
            'reason' => DefectReason::MasterError->value,
            'reported_by' => $this->author->id,
        ]);

        app(OrderBalance::class)->refresh($order);

        $entry = BonusEntry::query()->where('user_id', $master->id)->firstOrFail();
        $this->assertSame('1.00', $entry->base_amount->toString());
        $this->assertSame('0.00', (string) BonusEntry::where('user_id', $master->id)->sum('amount'));
        app(BonusAccrual::class)->reverseForOrder($order->fresh());
        $this->assertSame('0.00', (string) BonusEntry::where('user_id', $master->fresh()->id)->sum('amount'));
    }

    #[Test]
    public function a_return_reverses_the_active_bonus_entry(): void
    {
        $seller = User::factory()->for($this->branch)->create();
        $seller->assignRole(Role::Seller->value);
        $this->bonusRule(role: Role::Seller, base: BonusBase::Revenue, percent: '5.00');

        $order = $this->deliveredAndFullyPaidOrder($seller);
        app(OrderBalance::class)->refresh($order);

        $original = BonusEntry::query()->where('user_id', $seller->id)->firstOrFail();

        app(BonusAccrual::class)->reverseForOrder($order->fresh());

        $reversal = BonusEntry::query()->where('reverses_id', $original->id)->firstOrFail();
        $this->assertSame(BonusStatus::Reversed, $reversal->status);
        $this->assertSame('-10000.00', $reversal->amount->toString());
        $this->assertTrue($original->fresh()->isReversed());
    }

    #[Test]
    public function a_prepaid_order_accrues_its_bonus_when_delivered(): void
    {
        $seller = User::factory()->for($this->branch)->create();
        $seller->assignRole(Role::Seller->value);
        $this->bonusRule(Role::Seller, BonusBase::Revenue, '5.00');
        Location::factory()->for($this->branch)->create([
            'type' => LocationType::Warehouse,
        ]);
        $order = $this->deliveredAndFullyPaidOrder($seller);
        $order->update(['status' => OrderStatus::Ready, 'delivered_at' => null]);
        app(OrderBalance::class)->refresh($order);

        app(DeliverOrder::class)->handle($seller, $order);

        $this->assertSame(OrderStatus::Closed, $order->fresh()->status);
        $this->assertSame('10000.00', BonusEntry::query()->where('order_id', $order->id)->firstOrFail()->amount->toString());
    }

    #[Test]
    public function late_master_defects_recover_paid_earnings_once_and_carry_forward_within_the_month(): void
    {
        $master = User::factory()->for($this->branch)->create();
        $master->assignRole(Role::Master->value);
        $rule = $this->bonusRule(Role::Master, BonusBase::Count, '0');
        $rule->update(['rate' => '25000']);
        $order = $this->deliveredAndFullyPaidOrder($this->author);
        $work = WorkOrder::factory()->create(['order_id' => $order->id, 'master_id' => $master->id, 'branch_id' => $this->branch->id, 'status' => WorkOrderStatus::Done, 'finished_at' => now()]);
        app(OrderBalance::class)->refresh($order);
        BonusEntry::where('user_id', $master->id)->update(['status' => BonusStatus::Paid]);
        $defects = [];
        foreach (range(1, 2) as $i) {
            $defects[] = $defect = Defect::factory()->create(['work_order_id' => $work->id, 'reason' => DefectReason::MasterError, 'branch_id' => $this->branch->id]);
            app(BonusAccrual::class)->deductMasterDefect($defect);
            app(BonusAccrual::class)->deductMasterDefect($defect);
        }
        $this->assertSame('0.00', (string) BonusEntry::where('user_id', $master->id)->sum('amount'));
        $next = $this->deliveredAndFullyPaidOrder($this->author);
        WorkOrder::factory()->create(['order_id' => $next->id, 'master_id' => $master->id, 'branch_id' => $this->branch->id, 'status' => WorkOrderStatus::Done, 'finished_at' => now()]);
        app(OrderBalance::class)->refresh($next);
        $this->assertSame('0.00', (string) BonusEntry::where('user_id', $master->fresh()->id)->sum('amount'));
        $this->assertSame(2, BonusEntry::whereIn('defect_id', array_map(fn (Defect $defect): int => $defect->id, $defects))->count());
    }

    #[Test]
    public function two_master_orders_each_earn_one_monetary_rate(): void
    {
        $master = User::factory()->for($this->branch)->create();
        $master->assignRole(Role::Master->value);
        $rule = $this->bonusRule(Role::Master, BonusBase::Count, '0');
        $rule->update(['rate' => '25000']);
        foreach (range(1, 2) as $i) {
            $order = $this->deliveredAndFullyPaidOrder($this->author);
            WorkOrder::factory()->create([
                'order_id' => $order->id, 'branch_id' => $this->branch->id,
                'master_id' => $master->id, 'status' => WorkOrderStatus::Done,
                'finished_at' => now(), 'created_by' => $this->author->id,
            ]);
            app(OrderBalance::class)->refresh($order);
        }
        $this->assertSame('50000.00', (string) BonusEntry::where('user_id', $master->id)->sum('amount'));
    }

    #[Test]
    public function partial_returns_reverse_paid_bonuses_proportionally_and_idempotently(): void
    {
        $seller = User::factory()->for($this->branch)->create();
        $seller->assignRole(Role::Seller->value);
        $this->bonusRule(Role::Seller, BonusBase::Revenue, '5');
        $order = $this->deliveredAndFullyPaidOrder($seller);
        app(OrderBalance::class)->refresh($order);
        $original = BonusEntry::where('user_id', $seller->id)->firstOrFail();
        $original->update(['status' => BonusStatus::Paid]);
        $accrual = app(BonusAccrual::class);
        $accrual->reverseForOrder($order, '50000');
        $accrual->reverseForOrder($order, '50000');
        $this->assertSame('-2500.00', (string) BonusEntry::where('reverses_id', $original->id)->sum('amount'));
        $accrual->reverseForOrder($order, '200000');
        $this->assertSame('-10000.00', (string) BonusEntry::where('reverses_id', $original->id)->sum('amount'));
        $this->assertSame(BonusStatus::Paid, $original->fresh()->status);
    }

    private function bonusRule(Role $role, BonusBase $base, string $percent): BonusRule
    {
        return BonusRule::factory()->create([
            'role' => $role->value,
            'base' => $base->value,
            'percent' => $percent,
            'valid_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->author->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function deliveredAndFullyPaidOrder(User $creator, array $overrides = []): Order
    {
        $customerId = $overrides['customer_id'] ?? Customer::factory()->create()->id;

        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customerId,
            'created_by' => $creator->id,
            'status' => OrderStatus::Delivered,
            'total' => $overrides['total'] ?? '200000',
            'cost_total' => $overrides['cost_total'] ?? '0',
            'delivered_at' => now(),
            'prescription_id' => $overrides['prescription_id'] ?? null,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'branch_id' => $this->branch->id,
            'amount' => $order->total->toString(),
            'method' => PaymentMethod::Cash,
            'status' => PaymentTxStatus::Completed,
            'received_by' => $creator->id,
            'paid_at' => now(),
        ]);

        return $order;
    }
}
