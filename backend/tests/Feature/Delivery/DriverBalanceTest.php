<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * Haydovchi qo'lidagi pul va inkassatsiya — PROJECT.md §5.2, BOSQICH-8.md §3.
 *
 * Asosiy kafolat: yetkazish balansni oshiradi, inkassatsiya kamaytiradi
 * va kassaga tushadi; ortiqcha inkassatsiya rad etiladi.
 */
final class DriverBalanceTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    private User $driver;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10);

        $this->driver = User::factory()->for($this->branch)->create();
        $this->driver->assignRole(Role::Driver->value);

        $this->cashier = User::factory()->for($this->branch)->create();
        $this->cashier->assignRole(Role::Accountant->value);
    }

    #[Test]
    public function delivering_credits_the_balance_and_collecting_debits_it_into_the_register(): void
    {
        $this->collectCashViaDelivery('250000');

        $this->actingAs($this->cashier, 'sanctum');
        $this->getJson("/api/v1/driver-balances/{$this->driver->id}")
            ->assertOk()
            ->assertJsonPath('data.cash_amount', '250000.00');

        $this->postAction('/api/v1/collections', [
            'driver_id' => $this->driver->id,
            'branch_id' => $this->branch->id,
            'amount' => '250000',
        ])->assertCreated();

        $this->getJson("/api/v1/driver-balances/{$this->driver->id}")
            ->assertOk()
            ->assertJsonPath('data.cash_amount', '0.00');

        $movement = CashMovement::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(CashCategory::Collection, $movement->category);
        $this->assertSame('250000.00', $movement->amount->toString());
    }

    #[Test]
    public function collecting_more_than_the_driver_holds_is_refused(): void
    {
        $this->collectCashViaDelivery('100000');

        $this->actingAs($this->cashier, 'sanctum');
        $this->postAction('/api/v1/collections', [
            'driver_id' => $this->driver->id,
            'branch_id' => $this->branch->id,
            'amount' => '200000',
        ])->assertStatus(422);
    }

    #[Test]
    public function a_driver_only_sees_their_own_balance(): void
    {
        $this->collectCashViaDelivery('100000');

        $otherDriver = User::factory()->for($this->branch)->create();
        $otherDriver->assignRole(Role::Driver->value);

        $this->actingAs($otherDriver, 'sanctum');
        $this->getJson("/api/v1/driver-balances/{$this->driver->id}")->assertForbidden();

        $this->actingAs($this->driver, 'sanctum');
        $this->getJson("/api/v1/driver-balances/{$this->driver->id}")->assertOk();
    }

    private function collectCashViaDelivery(string $amount): void
    {
        $customer = Customer::factory()->create();

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $orderId = (int) $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(quantity: 1),
            'type' => 'order',
            'customer_id' => $customer->id,
        ])->json('data.id');

        $order = Order::findOrFail($orderId);
        $this->assertSame(OrderStatus::New, $order->status);

        $trip = Trip::factory()->create(['driver_id' => $this->driver->id]);
        $stop = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'customer_id' => $customer->id,
            'order_id' => $orderId,
            'cash_to_collect' => $amount,
        ]);

        $this->actingAs($this->driver, 'sanctum');
        $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/trip-stops/{$stop->id}/deliver")
            ->assertOk();
    }
}
