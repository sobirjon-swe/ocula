<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Device;
use App\Modules\Core\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Umumiy planshetda PIN bilan almashish — PROJECT.md 7.14.
 *
 * Kafolat: PIN **qurilma doirasida** tekshiriladi — boshqa filialdagi
 * yoki qurilmaga ruxsat etilmagan roldagi xodim kira olmaydi.
 */
final class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Device $device;

    private string $deviceToken = 'qurilma-tokeni-12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->branch = Branch::factory()->main()->create();
        $this->device = Device::factory()->for($this->branch)->create([
            'token' => $this->deviceToken,
            'allowed_roles' => [Role::Doctor->value, Role::Master->value],
        ]);

        RateLimiter::clear('pin|device:'.$this->device->id);
    }

    #[Test]
    public function it_issues_a_token_for_a_matching_pin(): void
    {
        $doctor = $this->employee(Role::Doctor, '1234');

        $response = $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $doctor->id)
            ->assertJsonStructure(['data' => ['token', 'user', 'permissions']]);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => $this->device->name]);
        $this->assertNotNull($this->device->fresh()?->last_seen_at);
    }

    #[Test]
    public function it_rejects_an_unknown_device_token(): void
    {
        $this->employee(Role::Doctor, '1234');

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => 'boshqa-token',
            'pin' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('device_token');
    }

    #[Test]
    public function it_rejects_a_deactivated_device(): void
    {
        $this->employee(Role::Doctor, '1234');
        $this->device->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('device_token');
    }

    #[Test]
    public function it_ignores_employees_of_another_branch(): void
    {
        $other = Branch::factory()->create();
        $stranger = User::factory()->for($other)->create(['pin_hash' => '1234']);
        $stranger->assignRole(Role::Doctor->value);

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }

    #[Test]
    public function it_ignores_roles_the_device_does_not_allow(): void
    {
        // Sotuvchi qurilmaning `allowed_roles` ro'yxatida yo'q.
        $this->employee(Role::Seller, '1234');

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }

    #[Test]
    public function it_ignores_a_deactivated_employee(): void
    {
        $this->employee(Role::Doctor, '1234', active: false);

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '1234',
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }

    #[Test]
    public function it_picks_the_right_employee_when_two_share_the_tablet(): void
    {
        $this->employee(Role::Doctor, '1111');
        $master = $this->employee(Role::Master, '2222');

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '2222',
        ])->assertOk()->assertJsonPath('data.user.id', $master->id);
    }

    #[Test]
    public function it_throttles_pin_guessing_per_device(): void
    {
        $this->employee(Role::Doctor, '1234');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/pin', [
                'device_id' => $this->device->id,
                'device_token' => $this->deviceToken,
                'pin' => '0000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '1234',
        ])->assertStatus(429);
    }

    #[Test]
    public function it_requires_a_pin_of_the_configured_length(): void
    {
        $this->postJson('/api/v1/auth/pin', [
            'device_id' => $this->device->id,
            'device_token' => $this->deviceToken,
            'pin' => '12',
        ])->assertStatus(422)->assertJsonValidationErrors('pin');
    }

    private function employee(Role $role, string $pin, bool $active = true): User
    {
        $user = User::factory()->for($this->branch)->create([
            'pin_hash' => Hash::make($pin),
            'is_active' => $active,
        ]);

        $user->assignRole($role->value);

        return $user;
    }
}
