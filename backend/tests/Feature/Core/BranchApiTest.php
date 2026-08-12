<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\BranchType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Device;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Filiallar CRUD — PERMISSIONS.md §1 matritsasi.
 *
 * `core.branch.*` to'liq faqat `director` da; `branch_manager` va
 * `accountant` da faqat `view`; `seller` da umuman yo'q.
 */
final class BranchApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    #[Test]
    public function a_director_lists_every_branch(): void
    {
        Branch::factory()->count(3)->create();
        $this->actingAsDirector();

        $this->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'code', 'type', 'users_count']], 'meta']);
    }

    #[Test]
    public function a_seller_may_not_list_branches(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);

        $this->getJson('/api/v1/branches')->assertForbidden();
    }

    #[Test]
    public function a_branch_manager_may_view_own_branch_but_not_another(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $this->actingAsEmployee(Role::BranchManager, $own);

        $this->getJson("/api/v1/branches/{$own->id}")->assertOk();
        $this->getJson("/api/v1/branches/{$other->id}")->assertForbidden();
    }

    #[Test]
    public function a_director_creates_a_branch_with_an_upper_case_code(): void
    {
        $this->actingAsDirector();

        $this->postJson('/api/v1/branches', [
            'name' => 'Chilonzor',
            'code' => 'f',
            'type' => BranchType::Shop->value,
            'phone' => '+998712000000',
        ])->assertCreated()->assertJsonPath('data.code', 'F');

        $this->assertDatabaseHas('branches', ['code' => 'F', 'name' => 'Chilonzor']);
    }

    #[Test]
    public function it_rejects_a_duplicate_branch_code(): void
    {
        Branch::factory()->create(['code' => 'A']);
        $this->actingAsDirector();

        $this->postJson('/api/v1/branches', [
            'name' => 'Yangi',
            'code' => 'A',
            'type' => BranchType::Shop->value,
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    #[Test]
    public function a_branch_manager_may_not_create_a_branch(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::BranchManager, $branch);

        $this->postJson('/api/v1/branches', [
            'name' => 'Yangi',
            'code' => 'Z',
            'type' => BranchType::Shop->value,
        ])->assertForbidden();
    }

    #[Test]
    public function the_code_can_not_be_changed_after_creation(): void
    {
        $branch = Branch::factory()->create(['code' => 'A']);
        $this->actingAsDirector();

        $this->putJson("/api/v1/branches/{$branch->id}", [
            'name' => 'Yangi nom',
            'code' => 'Z',
        ])->assertOk()->assertJsonPath('data.code', 'A');

        $this->assertSame('Yangi nom', $branch->fresh()?->name);
    }

    #[Test]
    public function an_empty_branch_can_be_deleted(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/branches/{$branch->id}")->assertNoContent();
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    #[Test]
    public function a_branch_with_employees_can_not_be_deleted(): void
    {
        $branch = Branch::factory()->create();
        User::factory()->for($branch)->create();
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/branches/{$branch->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('branch');

        $this->assertDatabaseHas('branches', ['id' => $branch->id]);
    }

    #[Test]
    public function a_branch_with_devices_can_not_be_deleted(): void
    {
        $branch = Branch::factory()->create();
        Device::factory()->for($branch)->create();
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/branches/{$branch->id}")->assertStatus(422);
    }

    #[Test]
    public function it_filters_and_sorts(): void
    {
        Branch::factory()->create(['name' => 'Chilonzor', 'code' => 'C', 'is_active' => true]);
        Branch::factory()->create(['name' => 'Yunusobod', 'code' => 'Y', 'is_active' => false]);
        $this->actingAsDirector();

        $this->getJson('/api/v1/branches?filter[is_active]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'C');

        $this->getJson('/api/v1/branches?filter[name]=yunus')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'Y');

        $this->getJson('/api/v1/branches?sort=-code')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'Y');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/v1/branches')->assertUnauthorized();
    }
}
