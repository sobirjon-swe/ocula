<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\DebtStatus;
use App\Modules\Finance\Models\Debt;
use App\Modules\Finance\Services\DebtRegistry;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Qarz registrining avtomatik sinxronlanishi — PROJECT.md 7.6,
 * BOSQICH-10.md §10a.
 *
 * Asosiy kafolatlar: `Debt` `orders.total`/`orders.paid` ning ko'zgusi;
 * to'liq to'lov qarzni yopadi; muddat o'tishi bilan holat vaqtga
 * bog'liq yangilanadi.
 */
final class DebtRegistryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Customer $customer;

    private DebtRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'K']);
        $this->customer = Customer::factory()->create();
        $this->registry = app(DebtRegistry::class);
    }

    #[Test]
    public function opening_a_debt_mirrors_the_order_totals(): void
    {
        $order = $this->orderWith(total: '200000', paid: '0', debt: '200000', dueDate: now()->addDays(5));

        $this->registry->syncForOrder($order);

        $debt = Debt::query()->firstOrFail();

        $this->assertSame($this->customer->id, $debt->customer_id);
        $this->assertSame($order->id, $debt->order_id);
        $this->assertSame('200000.00', $debt->amount->toString());
        $this->assertSame('0.00', $debt->paid->toString());
        $this->assertSame(DebtStatus::Open, $debt->status);
    }

    #[Test]
    public function a_partial_payment_updates_the_mirror_without_closing_it(): void
    {
        $order = $this->orderWith(total: '200000', paid: '0', debt: '200000', dueDate: now()->addDays(5));
        $this->registry->syncForOrder($order);

        $order->update(['paid' => '50000', 'debt' => '150000']);
        $this->registry->syncForOrder($order);

        $debt = Debt::query()->firstOrFail();

        $this->assertSame('50000.00', $debt->paid->toString());
        $this->assertSame('150000.00', $debt->remaining()->toString());
        $this->assertSame(DebtStatus::Open, $debt->status);
    }

    #[Test]
    public function a_full_payment_closes_the_debt(): void
    {
        $order = $this->orderWith(total: '200000', paid: '0', debt: '200000', dueDate: now()->addDays(5));
        $this->registry->syncForOrder($order);

        $order->update(['paid' => '200000', 'debt' => '0']);
        $this->registry->syncForOrder($order);

        $debt = Debt::query()->firstOrFail();

        $this->assertSame(DebtStatus::Paid, $debt->status);
        $this->assertNotNull($debt->closed_at);
    }

    #[Test]
    public function a_past_due_date_opens_the_debt_as_overdue(): void
    {
        $order = $this->orderWith(total: '100000', paid: '0', debt: '100000', dueDate: now()->subDays(3));

        $this->registry->syncForOrder($order);

        $this->assertSame(DebtStatus::Overdue, Debt::query()->firstOrFail()->status);
    }

    #[Test]
    public function refreshing_statuses_turns_a_long_overdue_debt_doubtful(): void
    {
        $debt = Debt::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->subDays(91)->toDateString(),
            'status' => DebtStatus::Open->value,
        ]);

        $changed = $this->registry->refreshStatuses();

        $this->assertSame(1, $changed);
        $this->assertSame(DebtStatus::Doubtful, $debt->fresh()->status);
    }

    #[Test]
    public function refreshing_statuses_never_touches_a_written_off_debt(): void
    {
        $debt = Debt::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'due_date' => now()->subDays(200)->toDateString(),
            'status' => DebtStatus::WrittenOff->value,
        ]);

        $this->registry->refreshStatuses();

        $this->assertSame(DebtStatus::WrittenOff, $debt->fresh()->status);
    }

    private function orderWith(string $total, string $paid, string $debt, CarbonImmutable|Carbon $dueDate): Order
    {
        $author = User::factory()->for($this->branch)->create();

        return Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by' => $author->id,
            'total' => $total,
            'paid' => $paid,
            'debt' => $debt,
            'due_date' => $dueDate->toDateString(),
        ]);
    }
}
