<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\BranchRankingService;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\PlanType;
use App\Modules\Payroll\Models\BranchPlan;
use App\Modules\Sales\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Filiallar reytingi — PROJECT.md 7.18.
 *
 * Asosiy kafolat: reyting **% bo'yicha**, mutlaq summa bo'yicha emas —
 * kichik filial rejasini ko'proq bajarsa, kattasidan yuqori turadi.
 */
final class BranchRankingTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->director = $this->employee(Role::Director);
    }

    #[Test]
    public function a_small_branch_beating_its_plan_outranks_a_big_branch_below_target(): void
    {
        $period = CarbonImmutable::today()->format('Y-m');

        $bigBranch = Branch::factory()->create(['code' => 'B']);
        $smallBranch = Branch::factory()->create(['code' => 'S']);

        BranchPlan::factory()->create([
            'branch_id' => $bigBranch->id,
            'period' => $period,
            'type' => PlanType::Revenue->value,
            'target_amount' => '10000000',
            'created_by' => $this->director->id,
        ]);
        BranchPlan::factory()->create([
            'branch_id' => $smallBranch->id,
            'period' => $period,
            'type' => PlanType::Revenue->value,
            'target_amount' => '1000000',
            'created_by' => $this->director->id,
        ]);

        // Katta filial: 5 000 000 / 10 000 000 = 50%.
        Order::factory()->create([
            'branch_id' => $bigBranch->id,
            'created_by' => $this->director->id,
            'total' => '5000000',
            'revenue_recognized_at' => now(),
        ]);

        // Kichik filial: 900 000 / 1 000 000 = 90% — mutlaq summada ancha kam,
        // lekin reja bajarilishida ustun.
        Order::factory()->create([
            'branch_id' => $smallBranch->id,
            'created_by' => $this->director->id,
            'total' => '900000',
            'revenue_recognized_at' => now(),
        ]);

        $ranking = app(BranchRankingService::class)->generate($this->director, $period);

        $this->assertSame($smallBranch->id, $ranking[0]['branch_id']);
        $this->assertSame('90.00', $ranking[0]['percent']);
        $this->assertSame($bigBranch->id, $ranking[1]['branch_id']);
        $this->assertSame('50.00', $ranking[1]['percent']);
    }
}
