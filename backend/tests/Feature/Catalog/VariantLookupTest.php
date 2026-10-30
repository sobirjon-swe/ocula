<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Core\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class VariantLookupTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    #[Test]
    public function a_seller_finds_a_variant_with_its_current_price_and_branch_balances(): void
    {
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(3);
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->getJson('/api/v1/catalog-variants?search='.$this->variant->sku.'&branch_id='.$this->branch->id)
            ->assertOk()->assertJsonPath('data.0.id', $this->variant->id)
            ->assertJsonPath('data.0.current_price', '250000.00')
            ->assertJsonPath('data.0.branch_balances.0.quantity', 3);
    }
}
