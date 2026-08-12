<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Variantlar va narxlar — SCHEMA.md §2, ANALIZ 3.16.
 *
 * Narx tarixi **yopiladi, o'chirilmaydi**: o'tgan oy hisoboti keyingi
 * narx o'zgarishidan buzilmasligi kerak.
 */
final class VariantPriceApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->product = Product::factory()->create([
            'created_by' => $this->employee(Role::Director)->id,
        ]);
    }

    #[Test]
    public function a_warehouse_keeper_adds_a_variant(): void
    {
        $this->actingAsEmployee(Role::Warehouse, Branch::factory()->create());

        $this->postJson("/api/v1/products/{$this->product->id}/variants", [
            'sph' => '-2.25',
            'cyl' => '-0.75',
            'axis' => 90,
            'sku' => 'HOYA-161-225',
        ])->assertCreated()
            ->assertJsonPath('data.sph', '-2.25')
            ->assertJsonPath('data.optical_label', 'SPH -2.25 · CYL -0.75 · AXIS 90');

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $this->product->id,
            'sku' => 'HOYA-161-225',
        ]);
    }

    #[Test]
    public function a_seller_may_not_add_a_variant(): void
    {
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->postJson("/api/v1/products/{$this->product->id}/variants", ['sph' => '-1.00'])
            ->assertForbidden();
    }

    #[Test]
    public function an_out_of_range_prescription_value_is_rejected(): void
    {
        $this->actingAsDirector();

        $this->postJson("/api/v1/products/{$this->product->id}/variants", ['sph' => '-99'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sph');

        $this->postJson("/api/v1/products/{$this->product->id}/variants", ['axis' => 200])
            ->assertStatus(422)
            ->assertJsonValidationErrors('axis');
    }

    #[Test]
    public function a_duplicate_barcode_is_rejected(): void
    {
        ProductVariant::factory()->for($this->product)->create(['barcode' => '4820001']);
        $this->actingAsDirector();

        $this->postJson("/api/v1/products/{$this->product->id}/variants", ['barcode' => '4820001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    /**
     * `/products/1/variants/99` da 99 boshqa tovarniki bo'lishi mumkin —
     * ikkala parametr mustaqil bog'lanadi.
     */
    #[Test]
    public function a_variant_of_another_product_is_not_reachable(): void
    {
        $other = Product::factory()->create(['created_by' => $this->employee(Role::Director)->id]);
        $variant = ProductVariant::factory()->for($other)->create();
        $this->actingAsDirector();

        $this->getJson("/api/v1/products/{$this->product->id}/variants/{$variant->id}")
            ->assertNotFound();
    }

    #[Test]
    public function setting_a_new_price_closes_the_previous_one(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();
        $this->actingAsDirector();

        $this->postJson("/api/v1/variants/{$variant->id}/prices", ['price' => '300000'])
            ->assertCreated()
            ->assertJsonPath('data.price', '300000.00')
            ->assertJsonPath('data.is_current', true);

        $this->postJson("/api/v1/variants/{$variant->id}/prices", ['price' => '350000'])
            ->assertCreated()
            ->assertJsonPath('data.price', '350000.00');

        // Eski yozuv o'chirilmaydi — yopiladi.
        $this->assertSame(2, Price::where('variant_id', $variant->id)->count());
        $this->assertSame(
            1,
            Price::where('variant_id', $variant->id)->whereNull('valid_to')->count(),
        );
    }

    #[Test]
    public function a_branch_price_beats_the_global_one(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();
        $branch = Branch::factory()->create();
        $this->actingAsDirector();

        $this->postJson("/api/v1/variants/{$variant->id}/prices", ['price' => '300000'])
            ->assertCreated();
        $this->postJson("/api/v1/variants/{$variant->id}/prices", [
            'price' => '280000',
            'branch_id' => $branch->id,
        ])->assertCreated();

        $this->getJson("/api/v1/variants/{$variant->id}/prices/current?branch_id={$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.price', '280000.00');

        // Boshqa filialda global narx qoladi.
        $this->getJson("/api/v1/variants/{$variant->id}/prices/current")
            ->assertOk()
            ->assertJsonPath('data.price', '300000.00');
    }

    #[Test]
    public function a_branch_price_does_not_close_the_global_chain(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();
        $branch = Branch::factory()->create();
        $this->actingAsDirector();

        $this->postJson("/api/v1/variants/{$variant->id}/prices", ['price' => '300000']);
        $this->postJson("/api/v1/variants/{$variant->id}/prices", [
            'price' => '280000',
            'branch_id' => $branch->id,
        ]);

        $global = Price::where('variant_id', $variant->id)->whereNull('branch_id')->firstOrFail();
        $this->assertNull($global->valid_to);
    }

    #[Test]
    public function a_seller_reads_prices_but_may_not_set_them(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->getJson("/api/v1/variants/{$variant->id}/prices")->assertOk();

        $this->postJson("/api/v1/variants/{$variant->id}/prices", ['price' => '100000'])
            ->assertForbidden();
    }

    #[Test]
    public function the_current_price_is_null_when_none_is_set(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();
        $this->actingAsDirector();

        $this->getJson("/api/v1/variants/{$variant->id}/prices/current")
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
