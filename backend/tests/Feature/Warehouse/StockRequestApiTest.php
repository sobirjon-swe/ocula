<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\StockRequestStatus;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\StockRequest;
use App\Modules\Warehouse\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Ichki so'rov — PROJECT.md §11 (Bosqich 4).
 *
 * Asosiy kafolat: so'rov va undan tug'ilgan transfer bir-biriga
 * bog'lanadi, shunda "nima uchun bu tovar yo'lga chiqdi" degan savolga
 * javob qoladi. Qaror esa tovar chiqadigan filialniki.
 */
final class StockRequestApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $asking;

    private Branch $supplying;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->asking = Branch::factory()->create(['code' => 'A']);
        $this->supplying = Branch::factory()->create(['code' => 'B']);

        Location::factory()->for($this->asking)->create(['type' => LocationType::Warehouse]);
        Location::factory()->for($this->supplying)->create(['type' => LocationType::Warehouse]);

        $author = User::factory()->for($this->asking)->create();

        $this->variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $author->id])
        )->create();
    }

    #[Test]
    public function a_seller_asks_another_branch_for_stock(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, $this->asking);

        $this->postAction('/api/v1/stock-requests', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', StockRequestStatus::Pending->value)
            ->assertJsonPath('data.from_branch_id', $this->asking->id)
            ->assertJsonPath('data.to_branch_id', $this->supplying->id);

        $this->assertSame($seller->id, StockRequest::query()->withoutGlobalScopes()
            ->firstOrFail()->requested_by);
    }

    #[Test]
    public function a_request_to_the_own_branch_is_refused(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->asking);

        $this->postAction('/api/v1/stock-requests', [
            ...$this->payload(),
            'to_branch_id' => $this->asking->id,
        ])->assertStatus(422)->assertJsonValidationErrors('to_branch_id');
    }

    /**
     * Qaror tovar chiqadigan filialniki: tovar ularning omborida
     * turibdi va ular uni o'z mijozlari uchun ham kerakligini biladi.
     */
    #[Test]
    public function the_supplying_branch_approves_the_request(): void
    {
        $id = $this->ask();

        $manager = $this->actingAsEmployee(Role::BranchManager, $this->supplying);

        $this->postAction("/api/v1/stock-requests/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', StockRequestStatus::Approved->value)
            ->assertJsonPath('data.approved_by', $manager->id);
    }

    #[Test]
    public function the_asking_branch_can_not_approve_its_own_request(): void
    {
        $id = $this->ask();

        $this->actingAsEmployee(Role::BranchManager, $this->asking);

        $this->postAction("/api/v1/stock-requests/{$id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function a_rejected_request_can_not_be_decided_again(): void
    {
        $id = $this->ask();

        $this->actingAsEmployee(Role::BranchManager, $this->supplying);

        $this->postAction("/api/v1/stock-requests/{$id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', StockRequestStatus::Rejected->value);

        $this->postAction("/api/v1/stock-requests/{$id}/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * So'rovdan transfer qoralamasi tug'iladi va ikkalasi bog'lanadi.
     * Yo'nalish teskari: tovar so'ralayotgan filialdan chiqadi.
     */
    #[Test]
    public function an_approved_request_turns_into_a_draft_transfer(): void
    {
        $id = $this->ask();

        $this->actingAsEmployee(Role::BranchManager, $this->supplying);
        $this->postAction("/api/v1/stock-requests/{$id}/approve")->assertOk();

        $this->postAction("/api/v1/stock-requests/{$id}/fulfill")
            ->assertCreated()
            ->assertJsonPath('data.status', TransferStatus::Draft->value);

        $request = StockRequest::query()->withoutGlobalScopes()->findOrFail($id);
        $this->assertSame(StockRequestStatus::Fulfilled, $request->status);
        $this->assertNotNull($request->transfer_id);

        $transfer = Transfer::withoutGlobalScopes()->findOrFail($request->transfer_id);

        $this->assertSame(
            $this->supplying->warehouse()?->id,
            $transfer->from_location_id,
        );
        $this->assertSame(
            $this->asking->warehouse()?->id,
            $transfer->to_location_id,
        );
    }

    #[Test]
    public function an_unapproved_request_can_not_be_fulfilled(): void
    {
        $id = $this->ask();

        $this->actingAsEmployee(Role::BranchManager, $this->supplying);

        $this->postAction("/api/v1/stock-requests/{$id}/fulfill")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('transfers', 0);
    }

    #[Test]
    public function the_asking_branch_may_take_its_request_back(): void
    {
        $id = $this->ask();

        $this->actingAsEmployee(Role::Seller, $this->asking);

        $this->postAction("/api/v1/stock-requests/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', StockRequestStatus::Cancelled->value);
    }

    /**
     * So'rovni **ikkala** filial ham ko'radi, uchinchisi ko'rmaydi.
     */
    #[Test]
    public function both_branches_see_the_request_and_a_third_does_not(): void
    {
        $id = $this->ask();

        $this->actingAsEmployee(Role::BranchManager, $this->supplying);
        $this->getJson("/api/v1/stock-requests/{$id}")->assertOk();

        $this->actingAsEmployee(Role::Seller, $this->asking);
        $this->getJson("/api/v1/stock-requests/{$id}")->assertOk();

        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());
        $this->getJson("/api/v1/stock-requests/{$id}")->assertNotFound();
    }

    #[Test]
    public function a_doctor_may_not_ask_for_stock(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->asking);

        $this->postAction('/api/v1/stock-requests', $this->payload())->assertForbidden();
    }

    /**
     * Filialsiz xodim (direktor) qaysi filial uchun so'rayotgani
     * noma'lum — so'rov yozib bo'lmaydi.
     */
    #[Test]
    public function a_user_without_a_branch_can_not_create_a_request(): void
    {
        $this->actingAsDirector();

        $this->postAction('/api/v1/stock-requests', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('from_branch_id');
    }

    private function ask(): int
    {
        $this->actingAsEmployee(Role::Seller, $this->asking);

        return (int) $this->postAction('/api/v1/stock-requests', $this->payload())
            ->assertCreated()
            ->json('data.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'to_branch_id' => $this->supplying->id,
            'variant_id' => $this->variant->id,
            'quantity' => 3,
        ];
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
