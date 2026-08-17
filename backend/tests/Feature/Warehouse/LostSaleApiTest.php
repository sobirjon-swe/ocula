<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Warehouse\Enums\LostSaleReason;
use App\Modules\Warehouse\Models\LostSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Yo'qotilgan savdo — PROJECT.md 7.9, ANALIZ.md 3.8.
 *
 * Asosiy kafolat: `variant_id` yoki `search_term` dan kamida bittasi
 * shart; sotuvchi yoza oladi, lekin doktor yozolmaydi.
 */
final class LostSaleApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->create(['code' => 'A']);
    }

    #[Test]
    public function a_seller_records_a_lost_sale_with_a_search_term(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postJson('/api/v1/lost-sales', [
            'branch_id' => $this->branch->id,
            'search_term' => 'Ray-Ban 3025',
            'reason' => LostSaleReason::NotInCatalog->value,
        ])->assertCreated()->assertJsonPath('data.search_term', 'Ray-Ban 3025');

        $this->assertSame(1, LostSale::query()->count());
    }

    #[Test]
    public function neither_variant_nor_search_term_is_rejected(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postJson('/api/v1/lost-sales', [
            'branch_id' => $this->branch->id,
            'reason' => LostSaleReason::OutOfStock->value,
        ])->assertStatus(422)->assertJsonValidationErrors('variant_id');
    }

    #[Test]
    public function a_doctor_can_not_record_a_lost_sale(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postJson('/api/v1/lost-sales', [
            'branch_id' => $this->branch->id,
            'search_term' => 'Test',
            'reason' => LostSaleReason::OutOfStock->value,
        ])->assertForbidden();
    }

    #[Test]
    public function a_warehouse_keeper_lists_lost_sales(): void
    {
        LostSale::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAsEmployee(Role::Warehouse, $this->branch);
        $this->getJson('/api/v1/lost-sales')->assertOk();
    }
}
