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
use App\Modules\Finance\Models\Expense;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Transfer;
use App\Modules\Warehouse\Models\TransferItem;
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
 * Filiallararo transfer — PROJECT.md 7.4, 7.10, ANALIZ 3.2.
 *
 * Asosiy kafolat: tovar yo'lda **yo'qolmaydi**. Jo'natilgach u transit
 * location'da turadi, qabul qilinganda yangi filialga o'tadi, yetmagan
 * qismi esa hisobdan chiqariladi — uch holatda ham umumiy qoldiq
 * tushuntirib beriladi.
 */
final class TransferApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $from;

    private Branch $to;

    private Location $fromWarehouse;

    private Location $toWarehouse;

    private ProductVariant $variant;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->from = Branch::factory()->main()->create(['code' => 'A']);
        $this->to = Branch::factory()->create(['code' => 'B']);

        $this->fromWarehouse = Location::factory()->for($this->from)
            ->create(['type' => LocationType::Warehouse]);
        $this->toWarehouse = Location::factory()->for($this->to)
            ->create(['type' => LocationType::Warehouse]);

        $author = User::factory()->for($this->from)->create();
        $this->driver = User::factory()->for($this->from)->create(['name' => 'Aziz']);

        $this->variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $author->id])
        )->create();

        app(StockLedger::class)->receive(
            $author, $this->fromWarehouse, $this->variant->id, 10, Money::of('75000'),
        );
    }

    #[Test]
    public function a_draft_does_not_touch_stock(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);

        $this->postAction('/api/v1/transfers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', TransferStatus::Draft->value);

        $this->assertSame(10, $this->balanceAt($this->fromWarehouse));
        $this->assertSame(0, StockMovement::query()->withoutGlobalScopes()
            ->where('type', MovementType::TransferOut->value)->count());
    }

    /**
     * Jo'natilgach tovar jo'natuvchidan chiqadi, lekin yo'qolmaydi —
     * u transitda turadi va tannarxini o'zi bilan olib ketadi.
     */
    #[Test]
    public function sending_moves_the_goods_into_transit_with_their_cost(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);

        $id = $this->postAction('/api/v1/transfers', $this->payload())->json('data.id');

        $this->postAction("/api/v1/transfers/{$id}/send", [
            'delivery_method' => DeliveryMethod::OwnDriver->value,
            'carrier_id' => $this->driver->id,
        ])->assertOk()->assertJsonPath('data.status', TransferStatus::Sent->value);

        $this->assertSame(6, $this->balanceAt($this->fromWarehouse));

        $transfer = Transfer::withoutGlobalScopes()->findOrFail((int) $id);
        $transit = Location::withoutGlobalScopes()->findOrFail($transfer->transit_location_id);

        $this->assertSame(4, $this->balanceAt($transit));
        $this->assertSame(LocationType::Transit, $transit->type);

        // Egasi haydovchi — yo'ldagi tovar javobgarsiz qolmaydi (3.2).
        $this->assertSame(User::class, $transit->owner_type);
        $this->assertSame($this->driver->id, $transit->owner_id);

        // Tannarx jo'natish paytida FIFO dan olindi va qotib qoldi.
        $this->assertSame('75000.00', TransferItem::firstOrFail()->unit_cost?->toString());
    }

    #[Test]
    public function receiving_everything_moves_the_goods_to_the_other_branch(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();

        $itemId = TransferItem::firstOrFail()->id;

        $this->actingAsEmployee(Role::Seller, $this->to);

        $this->postAction("/api/v1/transfers/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'quantity' => 4]],
        ])->assertOk()
            ->assertJsonPath('data.status', TransferStatus::Received->value)
            ->assertJsonPath('data.has_discrepancy', false);

        $this->assertSame(4, $this->balanceAt($this->toWarehouse));

        $transfer = Transfer::withoutGlobalScopes()->findOrFail((int) $id);
        $transit = Location::withoutGlobalScopes()->findOrFail($transfer->transit_location_id);

        // Transit bo'shadi — yo'lda hech narsa qolmadi.
        $this->assertSame(0, $this->balanceAt($transit));
    }

    /**
     * Kam kelgan tovar transitda osilib qolmaydi: u hisobdan
     * chiqariladi va direktorga signal beriladi (7.4).
     */
    #[Test]
    public function receiving_less_writes_off_the_difference_and_raises_a_flag(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();
        $itemId = TransferItem::firstOrFail()->id;

        $this->actingAsEmployee(Role::Seller, $this->to);

        $this->postAction("/api/v1/transfers/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'quantity' => 3]],
        ])->assertOk()
            ->assertJsonPath('data.status', TransferStatus::PartiallyReceived->value)
            ->assertJsonPath('data.has_discrepancy', true);

        $this->assertSame(3, $this->balanceAt($this->toWarehouse));

        $transfer = Transfer::withoutGlobalScopes()->findOrFail((int) $id);
        $transit = Location::withoutGlobalScopes()->findOrFail($transfer->transit_location_id);
        $this->assertSame(0, $this->balanceAt($transit));

        $writeOff = StockMovement::query()->withoutGlobalScopes()
            ->where('type', MovementType::WriteOff->value)
            ->firstOrFail();

        $this->assertSame(-1, $writeOff->quantity);
        $this->assertNotNull($writeOff->reason);
    }

    #[Test]
    public function more_than_sent_can_not_be_received(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();
        $itemId = TransferItem::firstOrFail()->id;

        $this->actingAsEmployee(Role::Seller, $this->to);

        $this->postAction("/api/v1/transfers/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'quantity' => 5]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, $this->balanceAt($this->toWarehouse));
    }

    #[Test]
    public function the_discrepancy_flag_is_cleared_by_the_director_with_a_reason(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();
        $itemId = TransferItem::firstOrFail()->id;

        $this->actingAsEmployee(Role::Seller, $this->to);
        $this->postAction("/api/v1/transfers/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'quantity' => 3]],
        ])->assertOk();

        $this->actingAsDirector();

        $this->postAction("/api/v1/transfers/{$id}/resolve-discrepancy", [
            'resolution' => 'Haydovchi tushirib qoldirgan, hisobdan chiqarildi',
        ])->assertOk()
            ->assertJsonPath('data.has_discrepancy', false)
            // Holat tarixiy fakt bo'lib qoladi — transfer haqiqatan kam kelgan.
            ->assertJsonPath('data.status', TransferStatus::PartiallyReceived->value);

        $this->assertStringContainsString(
            'Haydovchi tushirib qoldirgan',
            (string) Transfer::withoutGlobalScopes()->findOrFail((int) $id)->note,
        );
    }

    #[Test]
    public function a_seller_may_not_resolve_a_discrepancy(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();
        $itemId = TransferItem::firstOrFail()->id;

        $this->actingAsEmployee(Role::Seller, $this->to);
        $this->postAction("/api/v1/transfers/{$id}/receive", [
            'items' => [['item_id' => $itemId, 'quantity' => 3]],
        ])->assertOk();

        $this->postAction("/api/v1/transfers/{$id}/resolve-discrepancy", [
            'resolution' => 'Mayli',
        ])->assertForbidden();
    }

    /**
     * Taksida javobgar odam yo'q, shuning uchun xarajat majburiy (7.10)
     * va yo'ldagi tovar egasi transferning o'zi bo'ladi (3.2).
     */
    #[Test]
    public function a_taxi_transfer_requires_a_cost_and_owns_its_transit(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->postAction('/api/v1/transfers', $this->payload())->json('data.id');

        $this->postAction("/api/v1/transfers/{$id}/send", [
            'delivery_method' => DeliveryMethod::Taxi->value,
        ])->assertStatus(422)->assertJsonValidationErrors('taxi_cost');

        $this->postAction("/api/v1/transfers/{$id}/send", [
            'delivery_method' => DeliveryMethod::Taxi->value,
            'taxi_cost' => '35000',
        ])->assertOk()->assertJsonPath('data.taxi_cost', '35000.00');

        $transfer = Transfer::withoutGlobalScopes()->findOrFail((int) $id);
        $transit = Location::withoutGlobalScopes()->findOrFail($transfer->transit_location_id);

        $this->assertSame(Transfer::class, $transit->owner_type);
        $this->assertSame($transfer->id, $transit->owner_id);
    }

    /**
     * BOSQICH-4.md §5 #4 — yopildi (BOSQICH-10.md §10a). Taksi bilan
     * jo'natish avtomatik xarajat yaratadi, jo'natuvchi filial hisobiga.
     */
    #[Test]
    public function sending_by_taxi_writes_an_automatic_expense(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->postAction('/api/v1/transfers', $this->payload())->json('data.id');

        $this->postAction("/api/v1/transfers/{$id}/send", [
            'delivery_method' => DeliveryMethod::Taxi->value,
            'taxi_cost' => '35000',
        ])->assertOk();

        $expense = Expense::query()->withoutGlobalScopes()
            ->where('source_type', Transfer::class)
            ->where('source_id', $id)
            ->firstOrFail();

        $this->assertSame($this->from->id, $expense->branch_id);
        $this->assertSame('35000.00', $expense->amount->toString());
    }

    #[Test]
    public function sending_without_a_carrier_is_refused(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->postAction('/api/v1/transfers', $this->payload())->json('data.id');

        $this->postAction("/api/v1/transfers/{$id}/send", [
            'delivery_method' => DeliveryMethod::OwnDriver->value,
        ])->assertStatus(422)->assertJsonValidationErrors('carrier_id');

        $this->assertSame(10, $this->balanceAt($this->fromWarehouse));
    }

    #[Test]
    public function a_sent_transfer_can_not_be_cancelled(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();

        $this->postAction("/api/v1/transfers/{$id}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function a_draft_can_be_cancelled(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->postAction('/api/v1/transfers', $this->payload())->json('data.id');

        $this->postAction("/api/v1/transfers/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', TransferStatus::Cancelled->value);
    }

    #[Test]
    public function a_transfer_within_one_branch_is_refused(): void
    {
        $second = Location::factory()->for($this->from)->create(['type' => LocationType::Floor]);

        $this->actingAsEmployee(Role::Warehouse, $this->from);

        $this->postAction('/api/v1/transfers', [
            ...$this->payload(),
            'to_location_id' => $second->id,
        ])->assertStatus(422)->assertJsonValidationErrors('to_location_id');
    }

    /**
     * Transferni **ikkala** tomon ham ko'radi — qabul qiluvchi o'ziga
     * kelayotgan tovarni ko'rmasa, uni qabul ham qila olmasdi.
     */
    #[Test]
    public function both_sides_see_the_transfer_and_a_third_branch_does_not(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);
        $id = $this->sendTransfer();

        $this->actingAsEmployee(Role::Seller, $this->to);
        $this->getJson("/api/v1/transfers/{$id}")->assertOk();

        $third = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $third);
        $this->getJson("/api/v1/transfers/{$id}")->assertNotFound();

        $this->getJson('/api/v1/transfers')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Begona ombor `BranchScope` bilan umuman ko'rinmaydi — javob 404,
     * 403 emas: 403 "bunday ombor bor" degan ma'lumotni oshkor qilardi.
     */
    #[Test]
    public function a_foreign_source_warehouse_is_invisible(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->to);

        $this->postAction('/api/v1/transfers', $this->payload())->assertNotFound();

        $this->assertDatabaseCount('transfers', 0);
    }

    #[Test]
    public function creating_a_transfer_requires_an_idempotency_key(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->from);

        $this->postJson('/api/v1/transfers', $this->payload())->assertStatus(400);
    }

    private function sendTransfer(): int
    {
        $id = (int) $this->postAction('/api/v1/transfers', $this->payload())->json('data.id');

        $this->postAction("/api/v1/transfers/{$id}/send", [
            'delivery_method' => DeliveryMethod::OwnDriver->value,
            'carrier_id' => $this->driver->id,
        ])->assertOk();

        return $id;
    }

    private function balanceAt(Location $location): int
    {
        return app(StockLedger::class)->balanceOf($this->variant->id, $location->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'from_location_id' => $this->fromWarehouse->id,
            'to_location_id' => $this->toWarehouse->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
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
