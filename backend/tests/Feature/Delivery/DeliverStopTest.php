<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * Haydovchining "Yetkazdim" tasdig'i — PROJECT.md 7.4, BOSQICH-8.md §4.
 *
 * Asosiy kafolatlar: mijoz to'xtashi yetkazilganda buyurtma topshiriladi
 * va qarz bo'lsa dala to'lovi (`pending`) yaratiladi; boshqa haydovchi
 * bu to'xtashni yetkaza olmaydi.
 */
final class DeliverStopTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10);

        $this->driver = User::factory()->for($this->branch)->create();
        $this->driver->assignRole(Role::Driver->value);
    }

    #[Test]
    public function delivering_a_customer_stop_delivers_the_order_and_collects_the_debt_in_the_field(): void
    {
        Http::fake();

        $customer = Customer::factory()->create();
        $orderId = $this->createDeliverableOrder($customer, quantity: 1);
        $stop = $this->makeCustomerStop($orderId, cashToCollect: '250000');

        $this->actingAs($this->driver, 'sanctum');
        $this->deliver($stop->id)
            ->assertOk()
            ->assertJsonPath('data.status', TripStopStatus::Delivered->value)
            ->assertJsonPath('data.confirmation_status', ConfirmationStatus::Awaiting->value);

        $order = Order::findOrFail($orderId);
        $this->assertSame(OrderStatus::Closed, $order->status);
        $this->assertSame(9, $this->balance());

        $payment = Payment::query()->withoutGlobalScopes()->where('order_id', $orderId)->firstOrFail();
        $this->assertSame(PaymentTxStatus::Pending, $payment->status);
        $this->assertSame($this->driver->id, $payment->collected_by);
        $this->assertSame('250000.00', $payment->amount->toString());

        // Mijozning qarzi darrov kamaydi — pul haydovchida bo'lsa ham.
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        // Lekin kassaga hali tushmagan.
        $this->assertDatabaseCount('cash_movements', 0);
    }

    #[Test]
    public function another_driver_cannot_deliver_someone_elses_stop(): void
    {
        $customer = Customer::factory()->create();
        $orderId = $this->createDeliverableOrder($customer);
        $stop = $this->makeCustomerStop($orderId);

        $stranger = User::factory()->for($this->branch)->create();
        $stranger->assignRole(Role::Driver->value);

        $this->actingAs($stranger, 'sanctum');
        $this->deliver($stop->id)->assertForbidden();
    }

    #[Test]
    public function a_branch_stop_delivery_does_not_touch_the_transfer_receive_flow(): void
    {
        $trip = Trip::factory()->create(['driver_id' => $this->driver->id]);
        $stop = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'type' => TripStopType::Branch,
            'branch_id' => $this->branch->id,
            'customer_id' => null,
        ]);

        $this->actingAs($this->driver, 'sanctum');
        $this->deliver($stop->id)
            ->assertOk()
            ->assertJsonPath('data.status', TripStopStatus::Delivered->value)
            ->assertJsonPath('data.confirmation_status', null);

        $this->assertDatabaseCount('payments', 0);
    }

    private function createDeliverableOrder(Customer $customer, int $quantity = 1): int
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        return (int) $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(quantity: $quantity),
            'type' => 'order',
            'customer_id' => $customer->id,
        ])->json('data.id');
    }

    private function makeCustomerStop(int $orderId, string $cashToCollect = '0'): TripStop
    {
        $trip = Trip::factory()->create(['driver_id' => $this->driver->id]);
        $order = Order::findOrFail($orderId);

        return TripStop::factory()->create([
            'trip_id' => $trip->id,
            'type' => TripStopType::Customer,
            'customer_id' => $order->customer_id,
            'order_id' => $orderId,
            'cash_to_collect' => $cashToCollect,
        ]);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function deliver(int $stopId): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/trip-stops/{$stopId}/deliver");
    }
}
