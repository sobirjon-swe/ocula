<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use App\Modules\Payroll\Models\BonusRule;
use App\Modules\Payroll\Services\BonusRuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Mukofot qoidasini topish — PROJECT.md 7.19.
 *
 * Asosiy kafolat: shaxsiy qoida rol qoidasidan ustun; filialga mos rol
 * qoidasi tarmoq bo'yicha umumiysidan ustun; muddati o'tgan qoida
 * ishlatilmaydi.
 */
final class BonusRuleResolverTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private User $seller;

    private User $author;

    private BonusRuleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->create(['code' => 'A']);
        $this->author = User::factory()->create();
        $this->seller = $this->employee(Role::Seller, $this->branch);
        $this->resolver = app(BonusRuleResolver::class);
    }

    #[Test]
    public function a_personal_rule_beats_a_role_rule(): void
    {
        $this->roleRule(percent: '5.00');
        $personal = $this->personalRule(percent: '8.00');

        $resolved = $this->resolver->resolve($this->seller);

        $this->assertSame($personal->id, $resolved?->id);
        $this->assertSame('8.00', (string) $resolved->percent);
    }

    #[Test]
    public function a_branch_specific_role_rule_beats_a_network_wide_one(): void
    {
        $this->roleRule(percent: '5.00');
        $branchRule = $this->roleRule(percent: '7.00', branchId: $this->branch->id);

        $resolved = $this->resolver->resolve($this->seller);

        $this->assertSame($branchRule->id, $resolved?->id);
    }

    #[Test]
    public function an_expired_rule_is_not_used(): void
    {
        BonusRule::factory()->create([
            'role' => Role::Seller->value,
            'base' => BonusBase::Revenue->value,
            'percent' => '5.00',
            'valid_from' => now()->subYear()->toDateString(),
            'valid_to' => now()->subDay()->toDateString(),
            'created_by' => $this->author->id,
        ]);

        $this->assertNull($this->resolver->resolve($this->seller));
    }

    #[Test]
    public function a_rule_for_another_role_is_not_used(): void
    {
        BonusRule::factory()->create([
            'role' => Role::Master->value,
            'base' => BonusBase::Count->value,
            'percent' => '10.00',
            'valid_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->author->id,
        ]);

        $this->assertNull($this->resolver->resolve($this->seller));
    }

    private function roleRule(string $percent, ?int $branchId = null): BonusRule
    {
        return BonusRule::factory()->create([
            'role' => Role::Seller->value,
            'branch_id' => $branchId,
            'base' => BonusBase::Revenue->value,
            'percent' => $percent,
            'valid_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->author->id,
        ]);
    }

    private function personalRule(string $percent): BonusRule
    {
        return BonusRule::factory()->forUser($this->seller)->create([
            'base' => BonusBase::Revenue->value,
            'percent' => $percent,
            'valid_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->author->id,
        ]);
    }
}
