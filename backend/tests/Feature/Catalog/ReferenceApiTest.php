<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\ServiceType;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Service;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Ma'lumotnomalar — brend, kategoriya, xizmat (PERMISSIONS.md §2).
 *
 * Nozik joyi: **o'qish kengroq, o'zgartirish tor**. Sotuvchi tovar
 * kartochkasini to'ldirish uchun brendlarni ko'radi, lekin yangisini
 * qo'sha olmaydi — `catalog.brand.manage` faqat direktorda.
 */
final class ReferenceApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    #[Test]
    public function a_seller_may_read_brands_but_not_change_them(): void
    {
        Brand::factory()->create(['name' => 'Ray-Ban']);
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ray-Ban');

        $this->postJson('/api/v1/brands', ['name' => 'Yangi'])->assertForbidden();
    }

    #[Test]
    public function a_branch_manager_may_not_manage_brands(): void
    {
        $this->actingAsEmployee(Role::BranchManager, Branch::factory()->create());

        $this->postJson('/api/v1/brands', ['name' => 'Yangi'])->assertForbidden();
    }

    #[Test]
    public function a_director_creates_a_brand(): void
    {
        $this->actingAsDirector();

        $this->postJson('/api/v1/brands', ['name' => 'Hoya'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Hoya');

        $this->assertDatabaseHas('brands', ['name' => 'Hoya']);
    }

    #[Test]
    public function a_duplicate_brand_name_is_rejected(): void
    {
        Brand::factory()->create(['name' => 'Hoya']);
        $this->actingAsDirector();

        $this->postJson('/api/v1/brands', ['name' => 'Hoya'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function a_brand_with_products_can_not_be_deleted(): void
    {
        $brand = Brand::factory()->create();
        $director = $this->actingAsDirector();
        Product::factory()->create(['brand_id' => $brand->id, 'created_by' => $director->id]);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('brand');

        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    #[Test]
    public function an_unused_brand_is_deleted(): void
    {
        $brand = Brand::factory()->create();
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/brands/{$brand->id}")->assertNoContent();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    #[Test]
    public function the_category_list_is_returned_as_a_tree(): void
    {
        $parent = Category::factory()->create(['name' => 'Oynaklar', 'parent_id' => null]);
        Category::factory()->create(['name' => 'Quyoshdan', 'parent_id' => $parent->id]);
        $this->actingAsDirector();

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Oynaklar')
            ->assertJsonPath('data.0.children.0.name', 'Quyoshdan');
    }

    #[Test]
    public function a_category_can_not_become_its_own_parent(): void
    {
        $category = Category::factory()->create();
        $this->actingAsDirector();

        $this->putJson("/api/v1/categories/{$category->id}", ['parent_id' => $category->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    #[Test]
    public function a_category_with_children_can_not_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/categories/{$parent->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    #[Test]
    public function a_service_price_is_stored_with_two_decimals(): void
    {
        $this->actingAsDirector();

        $this->postJson('/api/v1/services', [
            'name' => "Ko'z tekshiruvi",
            'price' => '50000',
            'type' => ServiceType::Exam->value,
            'duration_min' => 20,
        ])->assertCreated()
            ->assertJsonPath('data.price', '50000.00')
            ->assertJsonPath('data.duration_min', 20);

        $this->assertDatabaseHas('services', ['name' => "Ko'z tekshiruvi", 'price' => '50000.00']);
    }

    #[Test]
    public function deleting_a_service_only_deactivates_it(): void
    {
        $service = Service::factory()->create(['is_active' => true]);
        $this->actingAsDirector();

        $this->deleteJson("/api/v1/services/{$service->id}")->assertNoContent();

        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);
    }

    #[Test]
    public function a_seller_may_not_manage_services(): void
    {
        $service = Service::factory()->create();
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->getJson('/api/v1/services')->assertOk();
        $this->deleteJson("/api/v1/services/{$service->id}")->assertForbidden();
    }
}
