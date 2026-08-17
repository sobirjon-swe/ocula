<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Modules\Warehouse\Actions\Transfer\CreateTransfer;
use App\Modules\Warehouse\Actions\Transfer\SendTransfer;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Models\Transfer;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Yo'l varaqasi — PROJECT.md §6.7, BOSQICH-8.md §5 #1, #2, #5.
 *
 * Asosiy kafolatlar: reys dispetcher tomonidan quriladi; filial
 * to'xtashi faqat allaqachon shu haydovchiga jo'natilgan transferni
 * oladi; faqat o'z haydovchisi (yoki dispetcher) reysni boshlaydi.
 */
final class TripApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private User $driver;

    private User $dispatcher;

    private ?Location $fromWarehouseCache = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->main()->create(['code' => 'A']);
        $this->driver = User::factory()->for($this->branch)->create(['name' => 'Aziz']);
        $this->driver->assignRole(Role::Driver->value);
        $this->dispatcher = User::factory()->for($this->branch)->create();
        $this->dispatcher->assignRole(Role::Director->value);
    }

    #[Test]
    public function a_dispatcher_creates_a_trip_for_a_driver(): void
    {
        $this->actingAs($this->dispatcher, 'sanctum');

        $this->postAction('/api/v1/trips', [
            'driver_id' => $this->driver->id,
            'date' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', TripStatus::Planned->value)
            ->assertJsonPath('data.driver_id', $this->driver->id);

        $this->assertSame(1, Trip::query()->count());
    }

    #[Test]
    public function a_driver_cannot_have_two_trips_on_the_same_day(): void
    {
        $this->actingAs($this->dispatcher, 'sanctum');
        $date = now()->toDateString();

        $this->postAction('/api/v1/trips', ['driver_id' => $this->driver->id, 'date' => $date])
            ->assertCreated();

        $this->postAction('/api/v1/trips', ['driver_id' => $this->driver->id, 'date' => $date])
            ->assertStatus(422);
    }

    #[Test]
    public function a_branch_stop_only_accepts_an_already_sent_transfer_for_the_same_driver(): void
    {
        $this->actingAs($this->dispatcher, 'sanctum');
        $trip = $this->postAction('/api/v1/trips', [
            'driver_id' => $this->driver->id, 'date' => now()->toDateString(),
        ])->json('data.id');

        // Hali jo'natilmagan transfer — rad etiladi.
        $draft = $this->createDraftTransfer();
        $this->postAction("/api/v1/trips/{$trip}/stops", [
            'type' => TripStopType::Branch->value,
            'transfer_id' => $draft->id,
        ])->assertStatus(422);

        // Jo'natilgan, aynan shu haydovchiga bog'langan transfer — qabul qilinadi.
        $sent = $this->sendTransfer($this->createDraftTransfer());
        $this->postAction("/api/v1/trips/{$trip}/stops", [
            'type' => TripStopType::Branch->value,
            'transfer_id' => $sent->id,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertSame($trip, $sent->fresh()->trip_id);
    }

    #[Test]
    public function a_customer_stop_defaults_the_cash_to_collect_to_the_remaining_debt(): void
    {
        $this->actingAs($this->dispatcher, 'sanctum');
        $trip = $this->postAction('/api/v1/trips', [
            'driver_id' => $this->driver->id, 'date' => now()->toDateString(),
        ])->json('data.id');

        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'total' => '200000',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'amount' => '50000',
            'method' => PaymentMethod::Cash,
            'status' => PaymentTxStatus::Completed,
            'received_by' => $this->dispatcher->id,
            'paid_at' => now(),
        ]);

        $this->postAction("/api/v1/trips/{$trip}/stops", [
            'type' => TripStopType::Customer->value,
            'order_id' => $order->id,
            'address' => 'Chilonzor, 12-uy',
        ])
            ->assertCreated()
            ->assertJsonPath('data.cash_to_collect', '150000.00');
    }

    #[Test]
    public function only_the_assigned_driver_or_a_dispatcher_can_start_a_trip(): void
    {
        $this->actingAs($this->dispatcher, 'sanctum');
        $tripId = $this->postAction('/api/v1/trips', [
            'driver_id' => $this->driver->id, 'date' => now()->toDateString(),
        ])->json('data.id');

        $otherDriver = User::factory()->for($this->branch)->create();
        $otherDriver->assignRole(Role::Driver->value);

        $this->actingAs($otherDriver, 'sanctum');
        $this->postAction("/api/v1/trips/{$tripId}/start")->assertForbidden();

        $this->actingAs($this->driver, 'sanctum');
        $this->postAction("/api/v1/trips/{$tripId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', TripStatus::InProgress->value);
    }

    private function createDraftTransfer(): Transfer
    {
        $to = Branch::factory()->create();
        $fromWarehouse = $this->fromWarehouse();
        $toWarehouse = Location::factory()->for($to)->create(['type' => LocationType::Warehouse]);

        $variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $this->dispatcher->id])
        )->create();

        app(StockLedger::class)->receive($this->dispatcher, $fromWarehouse, $variant->id, 10, Money::of('75000'));

        return app(CreateTransfer::class)->handle(
            $this->dispatcher, $fromWarehouse, $toWarehouse, [['variant_id' => $variant->id, 'quantity' => 2]],
        );
    }

    private function sendTransfer(Transfer $transfer): Transfer
    {
        return app(SendTransfer::class)->handle($this->dispatcher, $transfer, DeliveryMethod::OwnDriver, $this->driver->id);
    }

    /**
     * `warehouse` turi filialda bittadan bo'ladi (singleton) — bir necha
     * transfer yasashda qayta ishlatiladi.
     */
    private function fromWarehouse(): Location
    {
        return $this->fromWarehouseCache ??= Location::factory()
            ->for($this->branch)
            ->create(['type' => LocationType::Warehouse]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postAction(string $uri, array $payload = []): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($uri, $payload);
    }
}
