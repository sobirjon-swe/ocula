<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Xodimlar CRUD — PERMISSIONS.md §1.
 *
 * Nozik joylar: rol biriktirish va qarz limiti **faqat direktorda**,
 * o'chirish = faolsizlantirish, filial boshlig'i faqat o'z filialini
 * ko'radi.
 */
final class UserApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    #[Test]
    public function a_director_lists_employees_of_every_branch(): void
    {
        $a = Branch::factory()->create();
        $b = Branch::factory()->create();
        User::factory()->for($a)->create();
        User::factory()->for($b)->create();

        $director = $this->actingAsDirector();

        $this->getJson('/api/v1/users')
            ->assertOk()
            // Ikki xodim + direktorning o'zi.
            ->assertJsonCount(3, 'data');

        $this->assertNotNull($director->id);
    }

    #[Test]
    public function a_branch_manager_sees_only_own_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mine = User::factory()->for($own)->create();
        $stranger = User::factory()->for($other)->create();

        $this->actingAsEmployee(Role::BranchManager, $own);

        $response = $this->getJson('/api/v1/users')->assertOk();
        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($stranger->id, $ids);
    }

    #[Test]
    public function a_branch_manager_may_not_open_a_foreign_employee_card(): void
    {
        $own = Branch::factory()->create();
        $stranger = User::factory()->for(Branch::factory()->create())->create();

        $this->actingAsEmployee(Role::BranchManager, $own);

        $this->getJson("/api/v1/users/{$stranger->id}")->assertForbidden();
    }

    #[Test]
    public function a_director_creates_an_employee_with_roles(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsDirector();

        $this->postJson('/api/v1/users', [
            'name' => 'Aziz Sotuvchi',
            'phone' => '+998901234567',
            'password' => 'juda-kuchli-parol',
            'branch_id' => $branch->id,
            'roles' => [Role::Seller->value],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Aziz Sotuvchi')
            ->assertJsonPath('data.roles', [Role::Seller->value]);

        $created = User::where('phone', '+998901234567')->firstOrFail();
        $this->assertTrue($created->hasRole(Role::Seller->value));
        $this->assertNotSame('juda-kuchli-parol', $created->password);
    }

    #[Test]
    public function a_branch_manager_may_not_create_an_employee(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::BranchManager, $branch);

        $this->postJson('/api/v1/users', [
            'name' => 'Yangi',
            'phone' => '+998901234567',
            'password' => 'juda-kuchli-parol',
        ])->assertForbidden();
    }

    #[Test]
    public function it_rejects_a_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+998901234567']);
        $this->actingAsDirector();

        $this->postJson('/api/v1/users', [
            'name' => 'Ikkinchi',
            'phone' => '+998901234567',
            'password' => 'juda-kuchli-parol',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    #[Test]
    public function only_a_director_assigns_roles(): void
    {
        $branch = Branch::factory()->create();
        $employee = User::factory()->for($branch)->create();

        $this->actingAsEmployee(Role::BranchManager, $branch);
        $this->putJson("/api/v1/users/{$employee->id}/roles", [
            'roles' => [Role::Seller->value],
        ])->assertForbidden();

        $this->actingAsDirector();
        $this->putJson("/api/v1/users/{$employee->id}/roles", [
            'roles' => [Role::Seller->value, Role::Doctor->value],
        ])->assertOk()->assertJsonPath('data.roles', [Role::Seller->value, Role::Doctor->value]);
    }

    #[Test]
    public function the_customer_role_can_not_be_assigned_to_an_employee(): void
    {
        $employee = User::factory()->create();
        $this->actingAsDirector();

        $this->putJson("/api/v1/users/{$employee->id}/roles", [
            'roles' => [Role::Customer->value],
        ])->assertStatus(422)->assertJsonValidationErrors('roles.0');
    }

    #[Test]
    public function only_a_director_sets_the_debt_limit(): void
    {
        $branch = Branch::factory()->create();
        $seller = User::factory()->for($branch)->create();

        $this->actingAsEmployee(Role::BranchManager, $branch);
        $this->putJson("/api/v1/users/{$seller->id}/debt-limit", [
            'debt_limit' => '500000',
        ])->assertForbidden();

        $this->actingAsDirector();
        $this->putJson("/api/v1/users/{$seller->id}/debt-limit", [
            'debt_limit' => '500000',
        ])->assertOk()->assertJsonPath('data.debt_limit', '500000.00');
    }

    #[Test]
    public function a_seller_never_sees_the_debt_limit_field(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->actingAsEmployee(Role::Seller, $branch);

        // Sotuvchida `core.user.view` yo'q — o'z kartochkasini `me` orqali oladi.
        $response = $this->getJson('/api/v1/auth/me')->assertOk();

        $this->assertArrayNotHasKey('debt_limit', $response->json('data'));
        $this->assertSame($seller->id, $response->json('data.id'));
    }

    #[Test]
    public function setting_a_pin_never_returns_it(): void
    {
        $branch = Branch::factory()->create();
        $doctor = User::factory()->for($branch)->create();
        $this->actingAsDirector();

        $response = $this->putJson("/api/v1/users/{$doctor->id}/pin", ['pin' => '4321'])
            ->assertOk()
            ->assertJsonPath('data.has_pin', true);

        $this->assertArrayNotHasKey('pin', $response->json('data'));
        $this->assertArrayNotHasKey('pin_hash', $response->json('data'));

        $fresh = User::findOrFail($doctor->id);
        $this->assertNotNull($fresh->pin_hash);
        $this->assertTrue(Hash::check('4321', $fresh->pin_hash));
        $this->assertNotNull($fresh->pin_set_at);
    }

    #[Test]
    public function a_null_pin_removes_it(): void
    {
        $doctor = User::factory()->create(['pin_hash' => '1234']);
        $this->actingAsDirector();

        $this->putJson("/api/v1/users/{$doctor->id}/pin", ['pin' => null])
            ->assertOk()
            ->assertJsonPath('data.has_pin', false);

        $this->assertNull($doctor->fresh()?->pin_hash);
    }

    #[Test]
    public function it_rejects_a_pin_of_the_wrong_length(): void
    {
        $doctor = User::factory()->create();
        $this->actingAsDirector();

        $this->putJson("/api/v1/users/{$doctor->id}/pin", ['pin' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pin');
    }

    #[Test]
    public function deleting_an_employee_deactivates_and_revokes_tokens(): void
    {
        $employee = User::factory()->create(['is_active' => true]);
        $employee->createToken('Planshet');
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/users/{$employee->id}")->assertNoContent();

        $this->assertFalse($employee->fresh()?->is_active);
        $this->assertDatabaseHas('users', ['id' => $employee->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function a_director_can_not_delete_themselves(): void
    {
        $director = $this->actingAsDirector();

        $this->deleteJson("/api/v1/users/{$director->id}")->assertForbidden();
        $this->assertTrue($director->fresh()?->is_active);
    }

    #[Test]
    public function it_filters_by_role(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->employee(Role::Seller, $branch);
        $this->employee(Role::Doctor, $branch);
        $this->actingAsDirector();

        $response = $this->getJson('/api/v1/users?filter[role]='.Role::Seller->value)->assertOk();

        $this->assertSame([$seller->id], array_column($response->json('data'), 'id'));
    }
}
