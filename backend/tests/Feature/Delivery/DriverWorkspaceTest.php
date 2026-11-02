<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class DriverWorkspaceTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function goods_balance_includes_customer_packages_only_while_held_on_an_active_trip(): void
    {
        $this->seedPermissions();
        $driver = $this->actingAsEmployee(Role::Driver, Branch::factory()->create());
        $trip = Trip::factory()->create(['driver_id' => $driver->id, 'status' => 'in_progress']);
        $order = Order::factory()->create(['branch_id' => $driver->branch_id]);
        $stop = TripStop::factory()->create(['trip_id' => $trip->id, 'order_id' => $order->id]);
        $this->getJson('/api/v1/driver-balances/'.$driver->id)->assertOk()
            ->assertJsonPath('data.goods_quantity', 1)->assertJsonPath('data.customer_packages', 1);
        $stop->update(['status' => 'delivered']);
        $this->getJson('/api/v1/driver-balances/'.$driver->id)->assertOk()->assertJsonPath('data.goods_quantity', 0);
    }

    #[Test]
    public function a_driver_can_read_only_their_own_route(): void
    {
        $this->seedPermissions();
        $branch = Branch::factory()->create();
        $driver = $this->actingAsEmployee(Role::Driver, $branch);
        $mine = Trip::factory()->create(['driver_id' => $driver->id]);
        $other = Trip::factory()->create(['driver_id' => User::factory()->create()->id]);
        $this->getJson('/api/v1/trips')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson('/api/v1/trips/'.$other->id)->assertForbidden();
    }

    #[Test]
    public function a_delivered_stop_requires_gps_and_cannot_be_repeated_under_a_new_key(): void
    {
        $this->seedPermissions();
        $driver = $this->actingAsEmployee(Role::Driver, Branch::factory()->create());
        $trip = Trip::factory()->create(['driver_id' => $driver->id, 'status' => 'in_progress']);
        $stop = TripStop::factory()->create(['trip_id' => $trip->id, 'type' => 'branch', 'branch_id' => $driver->branch_id, 'customer_id' => null, 'order_id' => null]);
        $this->withHeader('Idempotency-Key', 'no-gps')->postJson("/api/v1/trip-stops/{$stop->id}/deliver")->assertUnprocessable();
        $this->withHeader('Idempotency-Key', 'delivery-a')->postJson("/api/v1/trip-stops/{$stop->id}/deliver", ['lat' => '41.31', 'lng' => '69.28'])->assertOk();
        $this->withHeader('Idempotency-Key', 'delivery-b')->postJson("/api/v1/trip-stops/{$stop->id}/deliver", ['lat' => '41.31', 'lng' => '69.28'])->assertUnprocessable();
    }
}
