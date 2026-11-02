<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class QuickProductWorkflowTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    #[Test]
    public function seller_registers_pending_identity_variant_and_first_branch_price_together(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/products/quick', [
            'name' => 'New frame', 'type' => 'frame', 'barcode' => '99880001', 'price' => '125000.50',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonCount(1, 'data.variants');
        $variantId = $response->json('data.variants.0.id');
        $this->assertDatabaseHas('product_variants', ['id' => $variantId, 'barcode' => '99880001']);
        $price = Price::query()->where('variant_id', $variantId)->firstOrFail();
        $this->assertSame('125000.50', $price->price->toString());
        $this->assertSame($branch->id, $price->branch_id);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    #[Test]
    public function duplicate_barcode_cannot_leave_a_partial_product(): void
    {
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());
        ProductVariant::factory()->create(['barcode' => 'existing']);
        $before = Product::count();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/products/quick', [
            'name' => 'Duplicate', 'type' => 'frame', 'barcode' => 'existing', 'price' => '100',
        ])->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->assertSame($before, Product::count());
    }

    #[Test]
    public function doctor_lookup_exposes_identity_only_without_debt_or_private_notes(): void
    {
        $this->actingAsEmployee(Role::Doctor, Branch::factory()->create());
        Customer::factory()->create(['name' => 'Ali Test', 'phone' => '+998901234567', 'notes' => 'private']);
        $response = $this->getJson('/api/v1/customers?filter[name]=Ali')->assertOk();
        $response->assertJsonPath('data.0.name', 'Ali Test');
        $this->assertSame(['id', 'name', 'phone'], array_keys($response->json('data.0')));
    }

    #[Test]
    public function opening_count_is_audited_once_with_fifo_cost_under_idempotency(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $branch);
        Location::factory()->create(['branch_id' => $branch->id, 'type' => 'warehouse']);
        $input = ['name' => 'Counted frame', 'type' => 'frame', 'price' => '100000', 'branch_id' => $branch->id, 'opening_quantity' => 3, 'cost_price' => '40000.50'];
        $key = (string) Str::uuid();
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/products/quick', $input)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/products/quick', $input)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', ['variant_id' => $first->json('data.variants.0.id'), 'quantity' => 3, 'type' => 'adjustment', 'source_type' => Product::class]);
        $this->assertDatabaseHas('stock_layers', ['variant_id' => $first->json('data.variants.0.id'), 'unit_cost' => '40000.50', 'quantity_remaining' => 3]);
    }

    #[Test]
    public function seller_cannot_record_an_opening_receipt_for_an_unassigned_branch(): void
    {
        $own = Branch::factory()->create();
        $foreign = Branch::factory()->create();
        $this->actingAsEmployee(Role::Seller, $own);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/products/quick', ['name' => 'Foreign', 'type' => 'frame', 'branch_id' => $foreign->id, 'opening_quantity' => 1, 'cost_price' => '100'])->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
