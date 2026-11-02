<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Service;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\Shift;
use App\Modules\Payroll\Models\BonusEntry;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Sales\Actions\Order\CancelOrder;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\CreditApproval;
use App\Modules\Warehouse\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class CheckoutCreditTest extends TestCase
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
    public function cancellation_rechecks_a_stale_order_and_clears_reversed_unfulfilled_debt(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = (int) $this->postAction('/api/v1/orders', [...$this->quickSalePayload(), 'type' => 'order'])->assertCreated()->json('data.id');
        $stale = Order::findOrFail($id);
        Order::whereKey($id)->update(['status' => 'delivered', 'delivered_at' => now()]);
        try {
            app(CancelOrder::class)->handle($stale);
            $this->fail('A stale order cannot overwrite a completed handover.');
        } catch (ValidationException) {
            $this->assertSame('delivered', Order::findOrFail($id)->status->value);
        }
        Order::whereKey($id)->update(['status' => 'new', 'delivered_at' => null]);
        $paymentId = $this->postAction("/api/v1/orders/$id/payments", ['amount' => '200000', 'method' => 'cash'])->assertCreated()->json('data.id');
        $this->actingAsDirector();
        $this->postAction("/api/v1/payments/$paymentId/reverse", ['reason' => 'Customer cancelled before production'])->assertCreated();
        $this->postAction("/api/v1/orders/$id/cancel")->assertOk()->assertJsonPath('data.debt', '0.00');
        $order = Order::findOrFail($id);
        $this->assertDatabaseHas('debts', ['order_id' => $id, 'status' => 'paid', 'amount' => '0.00']);
        $this->assertSame('0.00', $order->customer()->firstOrFail()->debt_balance->toString());
    }

    #[Test]
    public function a_manager_cannot_spend_their_own_allowance_to_bypass_the_original_seller_limit(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch)->update(['debt_limit' => '0']);
        $id = (int) $this->postAction('/api/v1/orders', [...$this->quickSalePayload(), 'type' => 'order'])->assertCreated()->json('data.id');
        Order::findOrFail($id)->update(['status' => 'ready']);
        $manager = $this->actingAsEmployee(Role::BranchManager, $this->branch);
        $this->postAction("/api/v1/orders/$id/deliver")->assertUnprocessable()->assertJsonValidationErrors('debt');
        $this->actingAsDirector();
        $this->postAction("/api/v1/orders/$id/approve-debt", ['reason' => 'Director approved the original seller credit sale'])->assertOk();
        $this->actingAs($manager, 'sanctum');
        $this->postAction("/api/v1/orders/$id/deliver")->assertOk();
    }

    #[Test]
    public function returns_follow_merged_variant_chains_without_rewriting_the_sale(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = (int) $this->postAction('/api/v1/checkout', [...$this->quickSalePayload(), 'payments' => [['amount' => '500000', 'method' => 'cash']]])->assertCreated()->json('data.id');
        $middle = ProductVariant::factory()->create();
        $target = ProductVariant::factory()->create();
        $this->variant->update(['merged_into_id' => $middle->id, 'is_active' => false]);
        $middle->update(['merged_into_id' => $target->id, 'is_active' => false]);
        $item = Order::findOrFail($id)->items()->firstOrFail();
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'refund' => '250000'])->assertCreated();
        $this->assertSame($this->variant->id, $item->fresh()->itemable_id);
        $this->assertDatabaseHas('return_items', ['order_item_id' => $item->id, 'variant_id' => $target->id]);
        $this->assertSame(1, app(StockLedger::class)->balanceOf($target->id, $this->warehouse->id));
    }

    #[Test]
    public function sellers_can_read_pos_rules_and_checkout_obeys_director_rounding_settings(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        Setting::put(SettingKey::MoneyRoundingStep, 1000);
        $this->getJson('/api/v1/pos-config')->assertOk()->assertJsonPath('data.money_rounding_step', '1000');
        Price::where('variant_id', $this->variant->id)->update(['price' => '249501']);
        $this->postAction('/api/v1/checkout', [...$this->quickSalePayload(), 'payments' => [['amount' => '499000', 'method' => 'cash']]])->assertCreated()->assertJsonPath('data.total', '499000.00')->assertJsonPath('data.status', 'closed');
        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $this->getJson('/api/v1/pos-config')->assertForbidden();
    }

    #[Test]
    public function reversing_payment_suspends_bonus_until_remaining_goods_are_paid_again(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, $this->branch);
        BonusRule::factory()->create(['user_id' => $seller->id, 'role' => null, 'base' => 'revenue', 'percent' => '10', 'valid_from' => now()->subDay()]);
        $id = (int) $this->postAction('/api/v1/checkout', [...$this->quickSalePayload(), 'payments' => [['amount' => '500000', 'method' => 'cash']]])->assertCreated()->json('data.id');
        $order = Order::findOrFail($id);
        $payment = $order->payments()->firstOrFail();
        $this->actingAsDirector();
        $this->postAction("/api/v1/payments/{$payment->id}/reverse", ['reason' => 'Wrong payment'])->assertCreated();
        $this->assertSame('delivered', $order->fresh()->status->value);
        $this->assertSame('0.00', (string) BonusEntry::where('user_id', $seller->id)->sum('amount'));
        $entry = BonusEntry::where('user_id', $seller->id)->firstOrFail();
        $this->postAction("/api/v1/bonus-entries/{$entry->id}/pay", ['branch_id' => $this->branch->id])->assertUnprocessable();
        $this->assertSame('accrued', $entry->fresh()->status->value);
        $item = $order->items()->firstOrFail();
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]])->assertCreated();
        $this->assertSame('250000.00', $order->fresh()->debt->toString());
        $this->assertSame('0.00', (string) BonusEntry::where('user_id', $seller->id)->sum('amount'));
        $this->postAction("/api/v1/orders/$id/payments", ['amount' => '250000', 'method' => 'cash'])->assertCreated();
        $this->assertSame('closed', $order->fresh()->status->value);
        $this->assertSame('25000.00', (string) BonusEntry::where('user_id', $seller->id)->sum('amount'));
        $this->assertSame(1, BonusEntry::where('user_id', $seller->id)->whereNull('reverses_id')->count());
        $this->postAction("/api/v1/bonus-entries/{$entry->id}/pay", ['branch_id' => $this->branch->id])->assertOk();
        $this->assertDatabaseHas('cash_movements', ['category' => 'bonus_payout', 'amount' => '25000', 'source_id' => $entry->id, 'source_type' => BonusEntry::class]);
    }

    #[Test]
    public function checkout_retries_do_not_duplicate_goods_cash_or_orders(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch)->update(['debt_limit' => '0']);
        $payload = [...$this->quickSalePayload(), 'payments' => [['amount' => '500000', 'method' => 'cash']]];
        $key = (string) Str::uuid();
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/checkout', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/checkout', $payload)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, Order::count());
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(8, $this->balance());
    }

    #[Test]
    public function credit_requires_permission_and_counts_existing_customer_exposure(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, $this->branch);
        $seller->update(['debt_limit' => '500000']);
        $customer = Customer::factory()->create();
        Order::factory()->delivered()->create(['customer_id' => $customer->id, 'branch_id' => $this->branch->id, 'total' => '100000', 'debt' => '100000']);
        $id = (int) $this->postAction('/api/v1/orders', [...$this->quickSalePayload(), 'type' => 'order', 'customer_id' => $customer->id])->assertCreated()->json('data.id');
        Order::findOrFail($id)->update(['status' => 'ready']);
        $this->postAction("/api/v1/orders/$id/deliver")->assertUnprocessable()->assertJsonValidationErrors('debt');
        $seller->update(['debt_limit' => '600000']);
        \Spatie\Permission\Models\Role::query()->where('name', 'seller')->where('guard_name', 'web')->firstOrFail()->revokePermissionTo('sales.customer.debt.grant');
        $seller->unsetRelation('roles');
        $this->postAction("/api/v1/orders/$id/deliver")->assertUnprocessable()->assertJsonValidationErrors('debt');
        $seller->givePermissionTo('sales.customer.debt.grant');
        $this->postAction("/api/v1/orders/$id/deliver")->assertOk();
    }

    #[Test]
    public function a_return_only_reverses_the_bonus_for_its_own_product_type(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->variant->product()->firstOrFail()->update(['type' => 'frame']);
        $service = $this->makeService('50000');
        foreach (['frame' => '10', 'service' => '20'] as $type => $percent) {
            BonusRule::factory()->create(['user_id' => $seller->id, 'role' => null, 'base' => 'revenue', 'product_type' => $type, 'percent' => $percent, 'valid_from' => now()->subDay()]);
        }
        $payload = $this->quickSalePayload([
            ['kind' => 'variant', 'id' => $this->variant->id, 'quantity' => 1],
            ['kind' => 'service', 'id' => $service->id, 'quantity' => 1],
        ]);
        $id = (int) $this->postAction('/api/v1/checkout', [...$payload, 'payments' => [['amount' => '300000', 'method' => 'cash']]])->assertCreated()->json('data.id');
        $item = Order::findOrFail($id)->items()->where('itemable_id', $service->id)->where('itemable_type', Service::class)->firstOrFail();
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'refund' => '50000'])->assertCreated();
        $this->assertSame('25000.00', (string) BonusEntry::where('user_id', $seller->id)->sum('amount'));
    }

    #[Test]
    public function a_partial_return_allocates_order_discount_and_reverses_only_that_share_of_bonus(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, $this->branch);
        $rule = BonusRule::factory()->create(['user_id' => $seller->id, 'role' => null, 'base' => 'revenue', 'percent' => '10', 'valid_from' => now()->subDay()]);
        $id = (int) $this->postAction('/api/v1/checkout', [...$this->quickSalePayload(), 'discount' => '10000', 'payments' => [['amount' => '490000', 'method' => 'cash']]])->assertCreated()->json('data.id');
        $item = Order::findOrFail($id)->items()->firstOrFail();
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'refund' => '245000'])->assertCreated()->assertJsonPath('data.amount', '245000.00');
        $this->assertSame('24500.00', (string) BonusEntry::where('user_id', $seller->id)->where('rule_id', $rule->id)->sum('amount'));
        $this->assertSame('0.00', app(CreditApproval::class)->exposure(Order::findOrFail($id))->toString());
    }

    #[Test]
    public function later_payments_and_returns_belong_to_the_current_shift(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $old = Shift::factory()->for($this->branch)->create();
        $id = (int) $this->postAction('/api/v1/orders', $this->quickSalePayload())->assertCreated()->json('data.id');
        $old->update(['status' => 'closed', 'closed_at' => now()]);
        $current = Shift::factory()->for($this->branch)->create();
        $this->postAction("/api/v1/orders/$id/payments", ['amount' => '500000', 'method' => 'cash'])->assertCreated();
        $this->assertDatabaseHas('payments', ['order_id' => $id, 'shift_id' => $current->id]);
        $item = Order::findOrFail($id)->items()->firstOrFail();
        $this->postAction("/api/v1/orders/$id/returns", ['reason' => 'other', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]], 'refund' => '250000'])->assertCreated();
        $this->assertDatabaseHas('returns', ['order_id' => $id, 'shift_id' => $current->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $id, 'amount' => '-250000', 'shift_id' => $current->id]);
    }

    #[Test]
    public function checkout_pays_before_handover_and_rolls_everything_back_on_failure(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch)->update(['debt_limit' => '0']);
        $payload = [...$this->quickSalePayload(), 'payments' => [['amount' => '500000', 'method' => 'cash']]];
        $this->postAction('/api/v1/checkout', $payload)->assertCreated()->assertJsonPath('data.status', 'closed');
        $this->assertSame(8, $this->balance());
        $payload['payments'][0]['amount'] = '500001';
        $this->postAction('/api/v1/checkout', $payload)->assertUnprocessable();
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $this->balance());
    }

    #[Test]
    public function unpaid_order_creation_is_allowed_but_handover_needs_credit_authority(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch)->update(['debt_limit' => '0']);
        $customer = Customer::factory()->create();
        $id = (int) $this->postAction('/api/v1/orders', [...$this->quickSalePayload(), 'type' => 'order', 'customer_id' => $customer->id])->assertCreated()->json('data.id');
        Order::findOrFail($id)->update(['status' => 'ready']);
        $this->postAction("/api/v1/orders/$id/deliver")->assertUnprocessable()->assertJsonValidationErrors('debt');
        $this->actingAsDirector();
        $this->postAction("/api/v1/orders/$id/approve-debt", ['reason' => 'Trusted customer'])->assertOk();
        $this->actingAsEmployee(Role::Seller, $this->branch)->update(['debt_limit' => '0']);
        $this->postAction("/api/v1/orders/$id/deliver")->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $id, 'debt_approval_reason' => 'Trusted customer']);
    }
}
