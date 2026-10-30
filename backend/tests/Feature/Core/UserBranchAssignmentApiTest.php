<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class UserBranchAssignmentApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    #[Test]
    public function director_assignments_change_branch_scope_and_record_actor_and_before_after_values(): void
    {
        $a = Branch::factory()->create();
        $b = Branch::factory()->create();
        $c = Branch::factory()->create();
        $locations = [];
        foreach ([$a, $b, $c] as $branch) {
            $locations[] = Location::factory()->for($branch)->create(['type' => LocationType::Warehouse]);
        }
        $employee = $this->employee(Role::Warehouse, $a);
        $this->actingAs($employee, 'sanctum');
        $this->getJson('/api/v1/locations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $locations[0]->id);
        $director = $this->actingAsDirector();
        $this->putJson("/api/v1/users/{$employee->id}/branches", ['branch_ids' => [$b->id]])->assertOk()->assertJsonPath('data.branch_ids', [$b->id]);
        $this->assertDatabaseHas('branch_user', ['user_id' => $employee->id, 'branch_id' => $b->id]);
        $log = Activity::where('subject_type', User::class)->where('subject_id', $employee->id)->where('event', 'branches_changed')->firstOrFail();
        $this->assertSame($director->id, $log->causer_id);
        $this->assertSame([], $log->properties->get('old')['branch_ids']);
        $this->assertSame([$b->id], $log->properties->get('attributes')['branch_ids']);
        $this->actingAs($employee->fresh(), 'sanctum');
        $this->getJson('/api/v1/locations')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $locations[0]->id)->assertJsonPath('data.1.id', $locations[1]->id);
        $this->actingAs($director, 'sanctum');
        $this->putJson("/api/v1/users/{$employee->id}/branches", ['branch_ids' => []])->assertOk()->assertJsonPath('data.branch_ids', []);
        $this->actingAs($employee->fresh(), 'sanctum');
        $this->getJson('/api/v1/locations')->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function sellers_cannot_assign_themselves_additional_branches(): void
    {
        $a = Branch::factory()->create();
        $b = Branch::factory()->create();
        $seller = $this->actingAsEmployee(Role::Seller, $a);
        $this->putJson("/api/v1/users/{$seller->id}/branches", ['branch_ids' => [$b->id]])->assertForbidden();
        $this->assertDatabaseMissing('branch_user', ['user_id' => $seller->id, 'branch_id' => $b->id]);
        $this->assertSame(0, Activity::where('subject_type', User::class)->where('subject_id', $seller->id)->where('event', 'branches_changed')->count());
    }
}
