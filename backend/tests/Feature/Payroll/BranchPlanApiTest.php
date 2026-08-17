<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Payroll\Enums\PlanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Filial oylik rejasi — PROJECT.md 7.18, PERMISSIONS.md §9.
 */
final class BranchPlanApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->create(['code' => 'A']);
    }

    #[Test]
    public function a_director_sets_a_branch_plan(): void
    {
        $this->actingAsDirector();

        $this->postAction('/api/v1/branch-plans', [
            'branch_id' => $this->branch->id,
            'period' => now()->format('Y-m'),
            'type' => PlanType::Revenue->value,
            'target_amount' => '50000000',
        ])->assertCreated()->assertJsonPath('data.target_amount', '50000000.00');
    }

    #[Test]
    public function a_branch_manager_may_view_but_not_set_a_plan(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $this->getJson('/api/v1/branch-plans')->assertOk();

        $this->postAction('/api/v1/branch-plans', [
            'branch_id' => $this->branch->id,
            'period' => now()->format('Y-m'),
            'type' => PlanType::Revenue->value,
            'target_amount' => '50000000',
        ])->assertForbidden();
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
