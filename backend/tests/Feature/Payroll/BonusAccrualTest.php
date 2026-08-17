<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Payroll\Services\BonusAccrual;
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
        // 1 ta yakunlangan ish − 1 ta o'z braki = 0 net.
        $this->assertSame('0.00', $entry->base_amount->toString());
        $this->assertSame('0.00', $entry->amount->toString());
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
