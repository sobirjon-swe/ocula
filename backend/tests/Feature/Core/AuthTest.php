<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Telefon + parol bilan kirish — PROJECT.md §9, SCHEMA.md §1.
 */
final class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login|+998901112233|127.0.0.1');
    }

    #[Test]
    public function it_issues_a_token_for_valid_credentials(): void
    {
        $branch = Branch::factory()->main()->create();
        $user = User::factory()->for($branch)->create([
            'phone' => '+998901112233',
            'password' => 'parol-12345',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901112233',
            'password' => 'parol-12345',
            'device_name' => 'Kassa kompyuteri',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.phone', '+998901112233')
            ->assertJsonStructure(['data' => ['token', 'user', 'permissions']]);

        $this->assertNotNull($user->fresh()?->last_login_at);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Kassa kompyuteri']);
    }

    #[Test]
    public function it_never_leaks_the_password_hash(): void
    {
        User::factory()->create(['phone' => '+998901112233', 'password' => 'parol-12345']);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901112233',
            'password' => 'parol-12345',
            'device_name' => 'Kassa',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('pin_hash', $response->json('data.user'));
    }

    #[Test]
    public function it_rejects_a_wrong_password_without_naming_the_field(): void
    {
        User::factory()->create(['phone' => '+998901112233', 'password' => 'parol-12345']);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901112233',
            'password' => 'boshqa-parol',
            'device_name' => 'Kassa',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_rejects_an_unknown_phone(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'phone' => '+998900000000',
            'password' => 'parol-12345',
            'device_name' => 'Kassa',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    #[Test]
    public function it_blocks_a_deactivated_employee(): void
    {
        User::factory()->create([
            'phone' => '+998901112233',
            'password' => 'parol-12345',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901112233',
            'password' => 'parol-12345',
            'device_name' => 'Kassa',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_throttles_after_five_failed_attempts(): void
    {
        User::factory()->create(['phone' => '+998901112233', 'password' => 'parol-12345']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => '+998901112233',
                'password' => 'notogri',
                'device_name' => 'Kassa',
            ])->assertStatus(422);
        }

        // To'g'ri parol ham o'tmaydi — qulf telefon + IP bo'yicha.
        $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901112233',
            'password' => 'parol-12345',
            'device_name' => 'Kassa',
        ])->assertStatus(429);
    }

    #[Test]
    public function it_returns_the_current_user_with_roles_and_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $branch = Branch::factory()->main()->create();
        $user = User::factory()->for($branch)->create();
        $user->assignRole(SpatieRole::findByName(Role::Seller->value, 'web'));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.branch.code', $branch->code)
            ->assertJsonPath('data.roles', [Role::Seller->value])
            ->assertJsonPath('meta.permissions.0', fn (mixed $value): bool => is_string($value));
    }

    #[Test]
    public function it_requires_authentication_for_me(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function it_revokes_only_the_current_token_on_logout(): void
    {
        $user = User::factory()->create();
        $keep = $user->createToken('Telefon')->plainTextToken;
        $revoke = $user->createToken('Kassa')->plainTextToken;

        $this->withToken($revoke)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Kassa']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Telefon']);

        // Saqlangan token hali ishlaydi.
        $this->withToken($keep)->getJson('/api/v1/auth/me')->assertOk();
    }
}
