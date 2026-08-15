<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\DeviceType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Device;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Qurilmalar — PROJECT.md 7.14, PERMISSIONS.md §1.
 *
 * Asosiy kafolatlar: token javobda **bir marta** chiqadi va bazada
 * faqat hash bo'lib qoladi; bekor qilingan qurilma bilan PIN orqali
 * kirib bo'lmaydi; qurilma o'chirilmaydi.
 */
final class DeviceApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->create(['code' => 'D']);
    }

    #[Test]
    public function registering_returns_the_token_exactly_once(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $response = $this->postJson('/api/v1/devices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ustaxona planshet')
            ->assertJsonPath('data.type', DeviceType::Tablet->value)
            ->assertJsonPath('data.is_active', true);

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertSame(48, strlen($token));

        // Bazada faqat hash — ochiq token saqlanmaydi.
        $device = Device::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertNotSame($token, $device->getAttribute('token'));
        $this->assertTrue(Hash::check($token, $device->getAttribute('token')));

        // Ro'yxatda va kartochkada token umuman ko'rinmaydi.
        $this->getJson("/api/v1/devices/{$device->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.token');
    }

    #[Test]
    public function the_registered_device_can_be_used_for_a_pin_login(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $token = $this->postJson('/api/v1/devices', $this->payload())
            ->assertCreated()
            ->json('data.token');

        $deviceId = Device::query()->withoutGlobalScopes()->firstOrFail()->id;

        $master = User::factory()->for($this->branch)->create(['pin_hash' => '1234']);
        $master->assignRole(Role::Master->value);

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $deviceId,
            'device_token' => $token,
            'pin' => '1234',
        ])->assertOk();
    }

    #[Test]
    public function a_revoked_device_stops_working(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $token = $this->postJson('/api/v1/devices', $this->payload())->json('data.token');
        $device = Device::query()->withoutGlobalScopes()->firstOrFail();

        $master = User::factory()->for($this->branch)->create(['pin_hash' => '1234']);
        $master->assignRole(Role::Master->value);

        $this->postJson("/api/v1/devices/{$device->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $device->id,
            'device_token' => $token,
            'pin' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('device_token');

        // Yozuv o'z joyida qoldi — kirish tarixi qaysi planshetdan
        // bo'lgani ko'rinib tursin.
        $this->assertDatabaseCount('devices', 1);
    }

    #[Test]
    public function the_allowed_roles_can_be_narrowed_later(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $this->postJson('/api/v1/devices', $this->payload())->assertCreated();
        $device = Device::query()->withoutGlobalScopes()->firstOrFail();

        $this->putJson("/api/v1/devices/{$device->id}", [
            'name' => 'Kassa POS',
            'allowed_roles' => ['seller'],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Kassa POS')
            ->assertJsonPath('data.allowed_roles', ['seller']);
    }

    #[Test]
    public function a_customer_role_is_not_a_valid_device_role(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $this->postJson('/api/v1/devices', [
            ...$this->payload(),
            'allowed_roles' => ['customer'],
        ])->assertStatus(422)->assertJsonValidationErrors('allowed_roles.0');
    }

    #[Test]
    public function a_seller_may_not_register_a_device(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postJson('/api/v1/devices', $this->payload())->assertForbidden();
    }

    #[Test]
    public function a_manager_may_not_register_a_device_in_a_foreign_branch(): void
    {
        $other = Branch::factory()->create();
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $this->postJson('/api/v1/devices', [
            ...$this->payload(),
            'branch_id' => $other->id,
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('devices', 0);
    }

    #[Test]
    public function the_list_is_limited_to_the_own_branch(): void
    {
        $mine = Device::factory()->for($this->branch)->create();
        Device::factory()->for(Branch::factory()->create())->create();

        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $response = $this->getJson('/api/v1/devices')->assertOk();

        $this->assertSame([$mine->id], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function a_director_sees_devices_of_every_branch(): void
    {
        Device::factory()->for($this->branch)->create();
        Device::factory()->for(Branch::factory()->create())->create();

        $this->actingAsDirector();

        $this->getJson('/api/v1/devices')->assertOk()->assertJsonCount(2, 'data');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'branch_id' => $this->branch->id,
            'name' => 'Ustaxona planshet',
            'type' => DeviceType::Tablet->value,
            'allowed_roles' => ['doctor', 'master'],
        ];
    }
}
