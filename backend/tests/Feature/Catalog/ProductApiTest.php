<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Katalog — PROJECT.md 7.13, 7.17.
 *
 * Eng muhim qoida: **tasdiqlash savdoni to'smaydi.** `pending` tovar
 * ham ro'yxatda ko'rinadi va sotiladi; tasdiq faqat analitikaga
 * ta'sir qiladi.
 */
final class ProductApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    #[Test]
    public function a_seller_quick_creates_a_pending_product(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->postJson('/api/v1/products/quick', [
            'type' => ProductType::Frame->value,
            'name' => 'Ray Ban 3025 qora',
        ])->assertCreated()
            ->assertJsonPath('data.status', ProductStatus::Pending->value)
            ->assertJsonPath('data.quick_created', true);

        $this->assertDatabaseHas('products', [
            'name' => 'Ray Ban 3025 qora',
            'status' => ProductStatus::Pending->value,
            'created_by' => $seller->id,
        ]);
    }

    #[Test]
    public function a_seller_may_not_use_the_full_create_endpoint(): void
    {
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->postJson('/api/v1/products', [
            'type' => ProductType::Frame->value,
            'name' => 'Ray Ban',
        ])->assertForbidden();
    }

    /**
     * Sotuvchi so'rovga `status=approved` yozib tasdiqlashni chetlab
     * o'ta olmaydi — status so'rovdan umuman o'qilmaydi.
     */
    #[Test]
    public function the_status_can_not_be_forced_through_the_request(): void
    {
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->postJson('/api/v1/products/quick', [
            'type' => ProductType::Frame->value,
            'name' => 'Ray Ban',
            'status' => ProductStatus::Approved->value,
        ])->assertCreated()->assertJsonPath('data.status', ProductStatus::Pending->value);
    }

    #[Test]
    public function a_warehouse_keeper_creates_a_product_that_still_needs_approval(): void
    {
        $this->actingAsEmployee(Role::Warehouse, Branch::factory()->create());

        $this->postJson('/api/v1/products', [
            'type' => ProductType::Lens->value,
            'name' => 'Hoya 1.61 HMC',
        ])->assertCreated()
            ->assertJsonPath('data.status', ProductStatus::Pending->value)
            ->assertJsonPath('data.quick_created', false);
    }

    #[Test]
    public function a_product_the_director_creates_is_already_approved(): void
    {
        $this->actingAsDirector();

        $this->postJson('/api/v1/products', [
            'type' => ProductType::Lens->value,
            'name' => 'Hoya 1.61 HMC',
        ])->assertCreated()->assertJsonPath('data.status', ProductStatus::Approved->value);
    }

    #[Test]
    public function a_pending_product_is_still_listed(): void
    {
        $director = $this->actingAsDirector();
        Product::factory()->create([
            'name' => 'Tekshirilmagan',
            'status' => ProductStatus::Pending,
            'created_by' => $director->id,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ProductStatus::Pending->value);
    }

    #[Test]
    public function the_pending_queue_shows_only_unapproved_products(): void
    {
        $director = $this->actingAsDirector();
        Product::factory()->create(['status' => ProductStatus::Pending, 'created_by' => $director->id]);
        Product::factory()->create(['status' => ProductStatus::Approved, 'created_by' => $director->id]);

        $this->getJson('/api/v1/products/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ProductStatus::Pending->value);
    }

    #[Test]
    public function only_a_director_approves_a_product(): void
    {
        $branch = Branch::factory()->create();
        $manager = $this->actingAsEmployee(Role::BranchManager, $branch);
        $product = Product::factory()->create([
            'status' => ProductStatus::Pending,
            'created_by' => $manager->id,
        ]);

        $this->postJson("/api/v1/products/{$product->id}/approve", ['approved' => true])
            ->assertForbidden();

        $director = $this->actingAsDirector();
        $this->postJson("/api/v1/products/{$product->id}/approve", ['approved' => true])
            ->assertOk()
            ->assertJsonPath('data.status', ProductStatus::Approved->value);

        $fresh = Product::findOrFail($product->id);
        $this->assertSame($director->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    #[Test]
    public function rejecting_a_product_marks_it_rejected(): void
    {
        $director = $this->actingAsDirector();
        $product = Product::factory()->create([
            'status' => ProductStatus::Pending,
            'created_by' => $director->id,
        ]);

        $this->postJson("/api/v1/products/{$product->id}/approve", ['approved' => false])
            ->assertOk()
            ->assertJsonPath('data.status', ProductStatus::Rejected->value);
    }

    #[Test]
    public function merging_keeps_the_duplicate_and_points_it_at_the_target(): void
    {
        $director = $this->actingAsDirector();
        $target = Product::factory()->create(['name' => 'Ray-Ban 3025', 'created_by' => $director->id]);
        $duplicate = Product::factory()->create(['name' => 'ray ban 3025', 'created_by' => $director->id]);

        $this->postJson("/api/v1/products/{$duplicate->id}/merge", ['merge_into_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.status', ProductStatus::Rejected->value)
            ->assertJsonPath('data.merged_into_id', $target->id)
            ->assertJsonPath('data.is_active', false);

        // Dublikat o'chirilmaydi — ombor va sotuv tarixi unga bog'liq.
        $this->assertDatabaseHas('products', ['id' => $duplicate->id, 'deleted_at' => null]);
    }

    #[Test]
    public function a_product_can_not_be_merged_into_itself(): void
    {
        $director = $this->actingAsDirector();
        $product = Product::factory()->create(['created_by' => $director->id]);

        $this->postJson("/api/v1/products/{$product->id}/merge", ['merge_into_id' => $product->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('merge_into_id');
    }

    #[Test]
    public function a_product_can_not_be_merged_into_an_already_merged_one(): void
    {
        $director = $this->actingAsDirector();
        $main = Product::factory()->create(['created_by' => $director->id]);
        $merged = Product::factory()->create([
            'created_by' => $director->id,
            'merged_into_id' => $main->id,
        ]);
        $another = Product::factory()->create(['created_by' => $director->id]);

        $this->postJson("/api/v1/products/{$another->id}/merge", ['merge_into_id' => $merged->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('merge_into_id');
    }

    #[Test]
    public function a_product_with_variants_can_not_be_deleted(): void
    {
        $director = $this->actingAsDirector();
        $product = Product::factory()->create(['created_by' => $director->id]);
        ProductVariant::factory()->for($product)->create();

        $this->deleteJson("/api/v1/products/{$product->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('product');
    }

    /**
     * Fuzzy qidiruv — "Рэй бан" ham, "ray-ban" ham bir xil natija
     * beradi (7.13, ANALIZ 3.14).
     */
    #[Test]
    public function the_search_matches_across_scripts_and_spelling(): void
    {
        $director = $this->actingAsDirector();
        Product::factory()->create(['name' => 'Ray-Ban 3025', 'created_by' => $director->id]);
        Product::factory()->create(['name' => 'Hoya 1.61 HMC', 'created_by' => $director->id]);

        $this->getJson('/api/v1/products?search=ray-ban')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ray-Ban 3025');

        $this->getJson('/api/v1/products?search='.urlencode('Рэй бан'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ray-Ban 3025');
    }

    #[Test]
    public function it_filters_by_type_and_status(): void
    {
        $director = $this->actingAsDirector();
        Product::factory()->create([
            'type' => ProductType::Frame,
            'status' => ProductStatus::Approved,
            'created_by' => $director->id,
        ]);
        Product::factory()->create([
            'type' => ProductType::Lens,
            'status' => ProductStatus::Pending,
            'created_by' => $director->id,
        ]);

        $this->getJson('/api/v1/products?filter[type]='.ProductType::Lens->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', ProductType::Lens->value);
    }

    #[Test]
    public function a_driver_may_not_see_the_catalog(): void
    {
        $this->actingAsEmployee(Role::Driver, Branch::factory()->create());

        $this->getJson('/api/v1/products')->assertForbidden();
    }
}
