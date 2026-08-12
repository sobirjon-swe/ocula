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
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Qoldiq va harakatlar daftari API — PROJECT.md 7.1, 7.21.
 *
 * Nozik joyi: **tannarx sotuvchiga chiqmaydi** — aks holda chegirma
 * berishda unga qarab savdolashadi (PERMISSIONS.md, nozikliklar #1).
 */
final class StockApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private Location $warehouse;

    private ProductVariant $variant;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->ledger = app(StockLedger::class);

        $this->branch = Branch::factory()->main()->create();
        $this->warehouse = Location::factory()->for($this->branch)
            ->create(['type' => LocationType::Warehouse]);

        $author = User::factory()->for($this->branch)->create();
        $this->variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $author->id])
        )->create();

        $this->ledger->receive($author, $this->warehouse, $this->variant->id, 10, Money::of('50000'));
    }

    #[Test]
    public function a_seller_sees_the_balance(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->getJson('/api/v1/stock/balances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', 10)
            ->assertJsonPath('data.0.variant_id', $this->variant->id);
    }

    #[Test]
    public function the_single_variant_balance_comes_from_the_ledger(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->getJson("/api/v1/stock/variants/{$this->variant->id}/locations/{$this->warehouse->id}")
            ->assertOk()
            ->assertJsonPath('data.quantity', 10);
    }

    /**
     * Sotuvchi qoldiqni ko'radi, lekin harakatlar daftarini emas —
     * `warehouse.stock.view` va `warehouse.movement.view_any` ataylab
     * ikki xil ruxsat (PERMISSIONS.md §3).
     */
    #[Test]
    public function a_seller_sees_the_balance_but_not_the_ledger(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->getJson('/api/v1/stock/balances')->assertOk();
        $this->getJson('/api/v1/stock/movements')->assertForbidden();
    }

    /**
     * Tannarx `catalog.cost.view` bilan qo'riqlanadi (nozikliklar #1).
     *
     * Hozirgi rol matritsasida daftarni ko'ra oladigan har kimda bu
     * ruxsat bor, shuning uchun kombinatsiya qo'lda yasaladi: himoya
     * rol ro'yxati o'zgarganda ham ishlashi kerak.
     */
    #[Test]
    public function the_cost_is_hidden_without_the_cost_permission(): void
    {
        $user = User::factory()->for($this->branch)->create();
        $user->givePermissionTo('warehouse.movement.view_any');

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/stock/movements')->assertOk();

        $this->assertArrayNotHasKey('cost_total', $response->json('data.0'));
        $this->assertArrayNotHasKey('cost_incomplete', $response->json('data.0'));

        $user->givePermissionTo('catalog.cost.view');

        $this->getJson('/api/v1/stock/movements')
            ->assertOk()
            ->assertJsonPath('data.0.cost_total', '500000.00');
    }

    #[Test]
    public function a_warehouse_keeper_sees_the_cost(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $this->getJson('/api/v1/stock/movements')
            ->assertOk()
            ->assertJsonPath('data.0.cost_total', '500000.00');
    }

    #[Test]
    public function the_movement_list_is_limited_to_the_own_branch(): void
    {
        $other = Branch::factory()->create();
        $otherWarehouse = Location::factory()->for($other)->create(['type' => LocationType::Warehouse]);
        $otherUser = User::factory()->for($other)->create();
        $this->ledger->receive($otherUser, $otherWarehouse, $this->variant->id, 4, Money::of('50000'));

        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $response = $this->getJson('/api/v1/stock/movements')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->branch->id, $response->json('data.0.branch_id'));
    }

    #[Test]
    public function only_a_director_reverses_a_movement(): void
    {
        $movement = StockMovement::withoutGlobalScopes()->firstOrFail();

        $this->actingAsEmployee(Role::Warehouse, $this->branch);
        $this->postAction("/api/v1/stock/movements/{$movement->id}/reverse", [
            'reason' => 'Hujjat xato kiritilgan',
        ])->assertForbidden();

        $this->actingAsDirector();
        $this->postAction("/api/v1/stock/movements/{$movement->id}/reverse", [
            'reason' => 'Hujjat xato kiritilgan',
        ])->assertCreated()->assertJsonPath('data.quantity', -10);

        $this->assertSame(0, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));
    }

    #[Test]
    public function a_reversal_requires_a_reason(): void
    {
        $movement = StockMovement::withoutGlobalScopes()->firstOrFail();
        $this->actingAsDirector();

        $this->postAction("/api/v1/stock/movements/{$movement->id}/reverse", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    #[Test]
    public function the_original_movement_survives_a_reversal(): void
    {
        $movement = StockMovement::withoutGlobalScopes()->firstOrFail();
        $this->actingAsDirector();

        $this->postAction("/api/v1/stock/movements/{$movement->id}/reverse", [
            'reason' => 'Hujjat xato',
        ])->assertCreated();

        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'quantity' => 10]);
        $this->assertDatabaseHas('stock_movements', [
            'reverses_id' => $movement->id,
            'quantity' => -10,
            'reason' => 'Hujjat xato',
        ]);
    }

    #[Test]
    public function a_driver_may_not_see_stock_at_all(): void
    {
        $this->actingAsEmployee(Role::Driver, $this->branch);

        $this->getJson('/api/v1/stock/movements')->assertForbidden();
    }

    #[Test]
    public function it_filters_movements_by_type(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $this->getJson('/api/v1/stock/movements?filter[type]=sale')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/stock/movements?filter[type]=purchase')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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
