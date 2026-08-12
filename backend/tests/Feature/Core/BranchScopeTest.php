<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Filial izolyatsiyasi — PROJECT.md §4, §14.
 *
 * Sotuvchi boshqa filial ma'lumotini **ko'rmasligi** kerak; direktor va
 * buxgalter esa hammasini ko'radi.
 */
final class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::staff() as $role) {
            SpatieRole::findOrCreate($role->value, 'web');
        }

        $this->branchA = Branch::factory()->main()->create(['code' => 'A']);
        $this->branchB = Branch::factory()->create(['code' => 'B']);

        Shift::factory()->for($this->branchA)->create();
        Shift::factory()->for($this->branchB)->create();
    }

    #[Test]
    public function a_seller_only_sees_their_own_branch(): void
    {
        $seller = $this->staff(Role::Seller, $this->branchA);

        $this->actingAs($seller);

        $shifts = Shift::all();

        $this->assertCount(1, $shifts);
        $this->assertSame($this->branchA->id, $shifts->first()?->branch_id);
    }

    #[Test]
    public function a_director_sees_every_branch(): void
    {
        $director = $this->staff(Role::Director, $this->branchA);

        $this->actingAs($director);

        $this->assertCount(2, Shift::all());
    }

    #[Test]
    public function an_accountant_sees_every_branch(): void
    {
        $accountant = $this->staff(Role::Accountant, $this->branchA);

        $this->actingAs($accountant);

        $this->assertCount(2, Shift::all());
    }

    #[Test]
    public function a_seller_with_pivot_access_sees_both_branches(): void
    {
        $seller = $this->staff(Role::Seller, $this->branchA);
        $seller->branches()->attach($this->branchB);

        $this->actingAs($seller);

        $this->assertCount(2, Shift::all());
    }

    #[Test]
    public function a_staff_member_without_any_branch_sees_nothing(): void
    {
        $orphan = User::factory()->create(['branch_id' => null]);
        $orphan->syncRoles([Role::Seller->value]);

        $this->actingAs($orphan);

        $this->assertCount(0, Shift::all());
    }

    #[Test]
    public function unauthenticated_context_is_not_scoped(): void
    {
        // Konsol, queue va seeder uchun cheklov qo'llanmaydi.
        $this->assertCount(2, Shift::all());
    }

    #[Test]
    public function a_new_record_inherits_the_users_branch(): void
    {
        // A va B da allaqachon ochiq smena bor (`shifts_one_open_per_branch`
        // ikkinchisiga yo'l qo'ymaydi), shuning uchun toza filial olamiz.
        $branchC = Branch::factory()->create(['code' => 'C']);
        $seller = $this->staff(Role::Seller, $branchC);

        $this->actingAs($seller);

        $shift = Shift::create([
            'opened_by' => $seller->id,
            'opened_at' => now(),
            'opening_cash' => '0.00',
            'status' => 'open',
        ]);

        $this->assertSame($branchC->id, $shift->branch_id);
    }

    #[Test]
    public function a_branch_may_have_only_one_open_shift(): void
    {
        // SCHEMA.md §1 dagi partial unique indeks.
        $this->expectException(QueryException::class);

        Shift::factory()->for($this->branchA)->create();
    }

    private function staff(Role $role, Branch $branch): User
    {
        $user = User::factory()->forBranch($branch)->create();
        $user->syncRoles([$role->value]);

        return $user;
    }
}
