<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Enums\ShiftStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Smena ochish/yopish — PROJECT.md 5.2, §15 #24.
 *
 * Kafolatlar: filialda bitta ochiq smena; kamomad `difference` da
 * manfiy chiqadi; sotuvchi begona filialga tegmaydi.
 */
final class ShiftApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    #[Test]
    public function a_seller_opens_a_shift_in_own_branch(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->actingAsEmployee(Role::Seller, $branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $branch->id,
            'opening_cash' => '150000',
        ])->assertCreated()
            ->assertJsonPath('data.status', ShiftStatus::Open->value)
            ->assertJsonPath('data.opening_cash', '150000.00')
            ->assertJsonPath('data.branch_id', $branch->id);

        $this->assertDatabaseHas('shifts', [
            'branch_id' => $branch->id,
            'opened_by' => $seller->id,
            'status' => ShiftStatus::Open->value,
        ]);
    }

    #[Test]
    public function a_second_shift_can_not_be_opened_in_the_same_branch(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $branch->id,
            'opening_cash' => '0',
        ])->assertCreated();

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $branch->id,
            'opening_cash' => '0',
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('shifts', 1);
    }

    #[Test]
    public function a_seller_may_not_open_a_shift_in_a_foreign_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $own);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $other->id,
            'opening_cash' => '0',
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('shifts', 0);
    }

    #[Test]
    public function a_doctor_may_not_open_a_shift(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Doctor, $branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $branch->id,
            'opening_cash' => '0',
        ])->assertForbidden();
    }

    #[Test]
    public function closing_computes_a_shortage_as_a_negative_difference(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->actingAsEmployee(Role::Seller, $branch);

        $shift = Shift::factory()->for($branch)->create([
            'opened_by' => $seller->id,
            'opening_cash' => '200000.00',
        ]);

        $this->postAction("/api/v1/shifts/{$shift->id}/close", [
            'actual_cash' => '185000',
            'note' => 'Seyfda kam chiqdi',
        ])->assertOk()
            ->assertJsonPath('data.status', ShiftStatus::Closed->value)
            ->assertJsonPath('data.expected_cash', '200000.00')
            ->assertJsonPath('data.actual_cash', '185000.00')
            ->assertJsonPath('data.difference', '-15000.00')
            ->assertJsonPath('data.has_shortage', true);

        $this->assertSame($seller->id, $shift->fresh()?->closed_by);
    }

    #[Test]
    public function closing_with_the_exact_amount_leaves_no_difference(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->actingAsEmployee(Role::Seller, $branch);

        $shift = Shift::factory()->for($branch)->create([
            'opened_by' => $seller->id,
            'opening_cash' => '200000.00',
        ]);

        $this->postAction("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => '200000'])
            ->assertOk()
            ->assertJsonPath('data.difference', '0.00')
            ->assertJsonPath('data.has_shortage', false);
    }

    #[Test]
    public function a_closed_shift_can_not_be_closed_again(): void
    {
        $branch = Branch::factory()->create();
        $seller = $this->actingAsEmployee(Role::Seller, $branch);

        $shift = Shift::factory()->for($branch)->create(['opened_by' => $seller->id]);

        $this->postAction("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => '0'])->assertOk();
        $this->postAction("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => '0'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift');
    }

    /**
     * `BranchScope` begona smenani route model binding bosqichidayoq
     * yashiradi — javob 403 emas, **404**. Bu ataylab: 403 "bunday
     * smena bor, lekin sizga emas" degan ma'lumotni oshkor qilardi.
     */
    #[Test]
    public function a_foreign_shift_is_invisible_not_merely_forbidden(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $own);

        $foreign = Shift::factory()->for($other)->create();

        $this->getJson("/api/v1/shifts/{$foreign->id}")->assertNotFound();

        $this->postAction("/api/v1/shifts/{$foreign->id}/close", ['actual_cash' => '0'])
            ->assertNotFound();

        $this->assertNull($foreign->fresh()?->closed_at);
    }

    #[Test]
    public function the_list_is_limited_to_the_own_branch(): void
    {
        $own = Branch::factory()->create();
        $other = Branch::factory()->create();
        $mine = Shift::factory()->for($own)->create();
        Shift::factory()->for($other)->create();

        $this->actingAsEmployee(Role::Seller, $own);

        $response = $this->getJson('/api/v1/shifts')->assertOk();

        $this->assertSame([$mine->id], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function a_director_sees_shifts_of_every_branch(): void
    {
        Shift::factory()->for(Branch::factory()->create())->create();
        Shift::factory()->for(Branch::factory()->create())->create();

        $this->actingAsDirector();

        $this->getJson('/api/v1/shifts')->assertOk()->assertJsonCount(2, 'data');
    }

    #[Test]
    public function the_current_endpoint_returns_null_when_nothing_is_open(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);

        $this->getJson('/api/v1/shifts/current')->assertOk()->assertJsonPath('data', null);
    }

    #[Test]
    public function the_current_endpoint_returns_the_open_shift(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);
        $shift = Shift::factory()->for($branch)->create();

        $this->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data.id', $shift->id);
    }

    #[Test]
    public function opening_a_shift_requires_an_idempotency_key(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);

        $this->postJson('/api/v1/shifts', [
            'branch_id' => $branch->id,
            'opening_cash' => '0',
        ])->assertStatus(400);
    }

    /**
     * Internet uzilib qayta yuborilganda ikkinchi smena ochilmaydi (§9).
     */
    #[Test]
    public function repeating_the_same_key_replays_the_first_response(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);

        $key = (string) Str::uuid();
        $payload = ['branch_id' => $branch->id, 'opening_cash' => '150000'];

        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/shifts', $payload)
            ->assertCreated();

        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/shifts', $payload)
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('shifts', 1);
    }

    /**
     * Amalni bajaruvchi so'rovlar `Idempotency-Key` talab qiladi (§9).
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postAction(string $uri, array $payload = []): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($uri, $payload);
    }
}
