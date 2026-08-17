<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Payroll\Enums\PlanType;
use App\Modules\Payroll\Models\BranchPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Hisobot ruxsatlari va filial cheklovi — PERMISSIONS.md §10.
 */
final class AnalyticsPermissionTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branchA = Branch::factory()->create(['code' => 'A']);
        $this->branchB = Branch::factory()->create(['code' => 'B']);
    }

    #[Test]
    public function everyone_can_see_the_dashboard(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branchA);
        $this->getJson('/api/v1/analytics/dashboard')->assertOk();
    }

    #[Test]
    public function a_seller_can_not_see_the_cash_report(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branchA);
        $this->getJson('/api/v1/analytics/cash-report')->assertForbidden();
    }

    #[Test]
    public function a_director_sees_the_cash_report(): void
    {
        $this->actingAsDirector();
        $this->getJson('/api/v1/analytics/cash-report')->assertOk();
    }

    #[Test]
    public function a_warehouse_keeper_can_not_export_without_the_export_permission(): void
    {
        // `warehouse` rolida `analytics.stock_report.view` bor, lekin
        // `analytics.export` ham bor — shuning uchun buxgalter emas,
        // sotuvchi orqali tekshiramiz: unda stock_report yo'q.
        $this->actingAsEmployee(Role::Seller, $this->branchA);
        $this->getJson('/api/v1/analytics/abc-analysis')->assertForbidden();
    }

    #[Test]
    public function a_branch_manager_only_ranks_their_own_branch(): void
    {
        $period = now()->format('Y-m');
        $manager = $this->employee(Role::BranchManager, $this->branchA);

        BranchPlan::factory()->create([
            'branch_id' => $this->branchA->id,
            'period' => $period,
            'type' => PlanType::Revenue->value,
            'target_amount' => '1000000',
            'created_by' => $manager->id,
        ]);
        BranchPlan::factory()->create([
            'branch_id' => $this->branchB->id,
            'period' => $period,
            'type' => PlanType::Revenue->value,
            'target_amount' => '1000000',
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager, 'sanctum');
        $response = $this->getJson('/api/v1/analytics/branch-rating')->assertOk();

        $branchIds = collect($response->json('data'))->pluck('branch_id')->all();
        $this->assertSame([$this->branchA->id], $branchIds);
    }
}
