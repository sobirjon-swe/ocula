<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Enums\SupplierTxType;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\SupplierTransaction;
use App\Modules\Finance\Services\SupplierLedger;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Yetkazib beruvchi bilan ikki tomonlama hisob — PROJECT.md 7.15,
 * BOSQICH-10.md §10a.
 *
 * Asosiy kafolat: kirim balansni kamaytiradi (biz qarzdor bo'lamiz),
 * to'lov balansni oshiradi va kassadan avtomatik chiqim yozadi.
 */
final class SupplierLedgerTest extends TestCase
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
        $this->supplier = Supplier::factory()->create(['payment_terms_days' => 14]);

        $author = User::factory()->for($this->branch)->create();
        $this->variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $author->id])
        )->create();
    }

    #[Test]
    public function receiving_a_purchase_lowers_the_balance_because_we_now_owe_the_supplier(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);

        $id = $this->postAction('/api/v1/purchases', [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'location_id' => $this->warehouse->id,
            'date' => now()->toDateString(),
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 10, 'cost_price' => '75000'],
            ],
        ])->json('data.id');

        $this->postAction("/api/v1/purchases/{$id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseStatus::Received->value);

        $ledger = app(SupplierLedger::class);
        $this->assertSame('-750000.00', $ledger->balance($this->supplier)->toString());

        $transaction = SupplierTransaction::query()->firstOrFail();
        $this->assertSame(SupplierTxType::Purchase, $transaction->type);
        $this->assertNotNull($transaction->due_date);
    }

    #[Test]
    public function a_payment_raises_the_balance_and_writes_a_cash_outflow(): void
    {
        $ledger = app(SupplierLedger::class);
        $author = $this->actingAsEmployee(Role::Accountant, $this->branch);

        $purchase = $this->receivedPurchase();
        $ledger->recordPurchase($author, $this->supplier, $purchase);

        $this->postAction('/api/v1/supplier-transactions', [
            'supplier_id' => $this->supplier->id,
            'type' => SupplierTxType::Payment->value,
            'branch_id' => $this->branch->id,
            'amount' => '300000',
        ])->assertCreated()->assertJsonPath('data.amount', '300000.00');

        $this->assertSame(
            $purchase->total->negated()->plus('300000')->toString(),
            $ledger->balance($this->supplier)->toString(),
        );

        $movement = CashMovement::query()->withoutGlobalScopes()
            ->where('category', CashCategory::SupplierPayment->value)
            ->firstOrFail();

        $this->assertSame('300000.00', $movement->amount->toString());
    }

    #[Test]
    public function a_seller_can_not_view_the_supplier_balance(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->getJson("/api/v1/suppliers/{$this->supplier->id}/balance")->assertForbidden();
    }

    private function receivedPurchase(): Purchase
    {
        $purchase = Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'location_id' => $this->warehouse->id,
            'total' => '750000',
            'status' => PurchaseStatus::Received->value,
        ]);

        return $purchase;
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
