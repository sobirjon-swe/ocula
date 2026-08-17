<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Payroll\Models\BonusEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Mukofot API — PERMISSIONS.md §9, BOSQICH-10.md §10b.
 *
 * Asosiy kafolat: `view_own` hammada bor (boshqasinikini ko'rmaydi);
 * to'lash kassadan chiqim yozadi va faqat direktor/buxgalterda bor.
 */
final class BonusApiTest extends TestCase
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
    public function an_employee_sees_only_their_own_bonus_entries(): void
    {
        $seller = $this->employee(Role::Seller, $this->branch);
        $other = $this->employee(Role::Seller, $this->branch);

        BonusEntry::factory()->create(['user_id' => $seller->id]);
        BonusEntry::factory()->create(['user_id' => $other->id]);

        $this->actingAs($seller, 'sanctum');
        $response = $this->getJson('/api/v1/bonus-entries')->assertOk();

        $ids = collect($response->json('data'))->pluck('user_id')->all();
        $this->assertSame([$seller->id], array_unique($ids));
    }

    #[Test]
    public function a_director_sees_everyones_bonus_entries(): void
    {
        $seller = $this->employee(Role::Seller, $this->branch);
        BonusEntry::factory()->create(['user_id' => $seller->id]);

        $this->actingAsDirector();
        $response = $this->getJson('/api/v1/bonus-entries')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function paying_a_bonus_writes_a_cash_outflow(): void
    {
        $seller = $this->employee(Role::Seller, $this->branch);
        $entry = BonusEntry::factory()->create([
            'user_id' => $seller->id,
            'amount' => '15000.00',
            'status' => BonusStatus::Accrued->value,
        ]);

        $this->actingAsDirector();
        $this->postAction("/api/v1/bonus-entries/{$entry->id}/pay", ['branch_id' => $this->branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', BonusStatus::Paid->value);

        $movement = CashMovement::query()->withoutGlobalScopes()
            ->where('category', CashCategory::BonusPayout->value)
            ->firstOrFail();

        $this->assertSame('15000.00', $movement->amount->toString());
    }

    #[Test]
    public function a_seller_can_not_pay_a_bonus(): void
    {
        $seller = $this->employee(Role::Seller, $this->branch);
        $entry = BonusEntry::factory()->create(['user_id' => $seller->id]);

        $this->actingAs($seller, 'sanctum');
        $this->postAction("/api/v1/bonus-entries/{$entry->id}/pay", ['branch_id' => $this->branch->id])
            ->assertForbidden();
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
