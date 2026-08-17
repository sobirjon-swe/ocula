<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsCustomer;
use Tests\TestCase;

/**
 * "Qabul qildim" / "Yo'q" — Mini App'dan, PROJECT.md 7.4, BOSQICH-9.md §1.
 *
 * Asosiy kafolat: o'z to'xtashini tasdiqlaydi/rad etadi; begonasini
 * qila olmaydi.
 */
final class DeliveryConfirmationTest extends TestCase
{
    use ActsAsCustomer, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCustomerPermissions();
    }

    #[Test]
    public function a_customer_confirms_their_own_delivery(): void
    {
        $customer = $this->actingAsCustomer();
        $stop = $this->makeStop($customer);

        $this->postAction("/api/v1/customer/trip-stops/{$stop->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.confirmation_status', ConfirmationStatus::Confirmed->value);

        $this->assertNotNull($stop->fresh()->customer_confirmed_at);
    }

    #[Test]
    public function a_customer_disputes_their_own_delivery(): void
    {
        $customer = $this->actingAsCustomer();
        $stop = $this->makeStop($customer);

        $this->postAction("/api/v1/customer/trip-stops/{$stop->id}/dispute")
            ->assertOk()
            ->assertJsonPath('data.confirmation_status', ConfirmationStatus::Disputed->value);
    }

    #[Test]
    public function a_customer_cannot_confirm_someone_elses_delivery(): void
    {
        $this->actingAsCustomer();
        $stop = $this->makeStop(Customer::factory()->create());

        $this->postAction("/api/v1/customer/trip-stops/{$stop->id}/confirm")->assertNotFound();
    }

    private function makeStop(Customer $customer): TripStop
    {
        $trip = Trip::factory()->create();

        return TripStop::factory()->create([
            'trip_id' => $trip->id,
            'type' => TripStopType::Customer,
            'customer_id' => $customer->id,
            'status' => TripStopStatus::Delivered,
            'delivered_at' => now(),
            'confirmation_status' => ConfirmationStatus::Awaiting,
        ]);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function postAction(string $uri): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($uri);
    }
}
