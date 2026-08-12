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
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\StockLayer;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Supplier;
use App\Modules\Warehouse\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Kirim hujjati — PROJECT.md 7.20, SCHEMA.md §3.
 *
 * Asosiy kafolat: `draft` holatida ombor **tegilmaydi**, qatlamlar
 * faqat qabul qilinganda ochiladi.
 */
final class PurchaseApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private Location $warehouse;

    private Supplier $supplier;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->main()->create(['code' => 'A']);
        $this->warehouse = Location::factory()->for($this->branch)
            ->create(['type' => LocationType::Warehouse]);
        $this->supplier = Supplier::factory()->create();

        $author = User::factory()->for($this->branch)->create();
        $this->variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $author->id])
        )->create();
    }

    #[Test]
    public function a_warehouse_keeper_creates_a_draft_that_does_not_touch_stock(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $response = $this->postAction('/api/v1/purchases', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', PurchaseStatus::Draft->value)
            ->assertJsonPath('data.total', '750000.00');

        // Raqam filial kodidan hosil bo'ladi (ANALIZ 3.12).
        $this->assertStringStartsWith('A-', (string) $response->json('data.number'));

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, StockLayer::count());
    }

    #[Test]
    public function receiving_opens_one_layer_per_line_and_raises_the_balance(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $id = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');

        $this->postAction("/api/v1/purchases/{$id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseStatus::Received->value);

        $this->assertSame(1, StockLayer::count());

        $layer = StockLayer::firstOrFail();
        $this->assertSame(10, $layer->quantity_in);
        $this->assertSame('75000.00', $layer->unit_cost->toString());

        $this->assertSame(
            10,
            app(StockLedger::class)->balanceOf($this->variant->id, $this->warehouse->id),
        );
    }

    #[Test]
    public function the_movements_point_back_at_the_purchase(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $id = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');
        $this->postAction("/api/v1/purchases/{$id}/receive")->assertOk();

        $movement = StockMovement::firstOrFail();
        $this->assertSame(Purchase::class, $movement->source_type);
        $this->assertSame((int) $id, $movement->source_id);
    }

    #[Test]
    public function a_purchase_can_not_be_received_twice(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $id = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');
        $this->postAction("/api/v1/purchases/{$id}/receive")->assertOk();

        $this->postAction("/api/v1/purchases/{$id}/receive")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        // Ikkinchi marta qatlam ochilmagan.
        $this->assertSame(1, StockLayer::count());
    }

    #[Test]
    public function a_seller_may_not_create_or_receive_a_purchase(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/purchases', $this->payload())->assertForbidden();
    }

    #[Test]
    public function a_draft_can_be_cancelled_but_a_received_one_can_not(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $draft = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');
        $this->postAction("/api/v1/purchases/{$draft}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseStatus::Cancelled->value);

        $received = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');
        $this->postAction("/api/v1/purchases/{$received}/receive")->assertOk();

        $this->postAction("/api/v1/purchases/{$received}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function a_cancelled_purchase_can_not_be_received(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $id = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');
        $this->postAction("/api/v1/purchases/{$id}/cancel")->assertOk();

        $this->postAction("/api/v1/purchases/{$id}/receive")->assertStatus(422);
        $this->assertSame(0, StockMovement::count());
    }

    #[Test]
    public function a_location_of_another_branch_is_rejected(): void
    {
        $this->actingAsDirector();

        $foreign = Location::factory()->for(Branch::factory()->create())
            ->create(['type' => LocationType::Warehouse]);

        $this->postAction('/api/v1/purchases', [
            ...$this->payload(),
            'location_id' => $foreign->id,
        ])->assertStatus(422)->assertJsonValidationErrors('location_id');
    }

    /**
     * `BranchScope` begona omborni umuman ko'rsatmaydi — javob 404,
     * 403 emas: 403 "bunday ombor bor" degan ma'lumotni oshkor qilardi.
     */
    #[Test]
    public function a_foreign_warehouse_is_invisible(): void
    {
        $other = Branch::factory()->create();
        $otherWarehouse = Location::factory()->for($other)->create(['type' => LocationType::Warehouse]);

        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $this->postAction('/api/v1/purchases', [
            ...$this->payload(),
            'branch_id' => $other->id,
            'location_id' => $otherWarehouse->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('purchases', 0);
    }

    /**
     * O'z omborini ko'rsatib, filialni begonasiga almashtirib bo'lmaydi.
     */
    #[Test]
    public function a_warehouse_keeper_may_not_buy_into_a_foreign_branch(): void
    {
        $other = Branch::factory()->create();

        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $this->postAction('/api/v1/purchases', [
            ...$this->payload(),
            'branch_id' => $other->id,
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('purchases', 0);
    }

    #[Test]
    public function the_list_is_limited_to_the_own_branch(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);
        $mine = $this->postAction('/api/v1/purchases', $this->payload())->json('data.id');

        $other = Branch::factory()->create();
        $otherWarehouse = Location::factory()->for($other)->create(['type' => LocationType::Warehouse]);
        $this->actingAsEmployee(Role::Warehouse, $other);
        $this->postAction('/api/v1/purchases', [
            ...$this->payload(),
            'branch_id' => $other->id,
            'location_id' => $otherWarehouse->id,
        ])->assertCreated();

        // Birinchi filialga qaytamiz.
        $this->actingAsEmployee(Role::Warehouse, $this->branch);
        $response = $this->getJson('/api/v1/purchases')->assertOk();

        $this->assertSame([$mine], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function an_empty_document_is_rejected_by_validation(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $this->postAction('/api/v1/purchases', [
            ...$this->payload(),
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
    }

    #[Test]
    public function creating_a_purchase_requires_an_idempotency_key(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $this->postJson('/api/v1/purchases', $this->payload())->assertStatus(400);
    }

    /**
     * Filialda internet uzilib qayta yuborilganda ikkinchi kirim
     * yaratilmaydi (§9).
     */
    #[Test]
    public function repeating_the_same_key_does_not_create_a_second_purchase(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $key = (string) Str::uuid();
        $payload = $this->payload();

        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/purchases', $payload)->assertCreated();
        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/purchases', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('purchases', 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'location_id' => $this->warehouse->id,
            'date' => now()->toDateString(),
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 10, 'cost_price' => '75000'],
            ],
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
