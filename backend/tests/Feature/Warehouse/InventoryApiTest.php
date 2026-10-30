<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class InventoryApiTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10, '75000');
        $this->actingAsDirector();
    }

    #[Test]
    public function sellers_can_count_stock_without_receiving_inventory_unit_costs(): void
    {
        $id = (int) $this->postAction('/api/v1/inventories', ['location_id' => $this->warehouse->id])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/inventories/{$id}/counts", ['items' => [
            ['variant_id' => $this->variant->id, 'actual_qty' => 10, 'unit_cost' => '75000'],
        ]])->assertOk()->assertJsonPath('data.items.0.unit_cost', '75000.00');
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->getJson("/api/v1/inventories/{$id}")->assertOk()
            ->assertJsonPath('data.items.0.expected_qty', 10)->assertJsonMissingPath('data.items.0.unit_cost');
        $this->putJson("/api/v1/inventories/{$id}/counts", ['items' => [
            ['variant_id' => $this->variant->id, 'actual_qty' => 9],
        ]])->assertOk()->assertJsonPath('data.items.0.actual_qty', 9)->assertJsonMissingPath('data.items.0.unit_cost');
        $this->postAction("/api/v1/inventories/{$id}/complete")->assertForbidden();
        $this->assertSame(10, $this->balance());
    }

    #[Test]
    public function a_count_records_the_fifo_shortage_once_and_keeps_the_ledger_balanced(): void
    {
        $id = $this->postAction('/api/v1/inventories', ['location_id' => $this->warehouse->id])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/inventories/{$id}/counts", ['items' => [
            ['variant_id' => $this->variant->id, 'actual_qty' => 7],
        ]])->assertOk();
        $this->postAction("/api/v1/inventories/{$id}/review")->assertOk();
        $this->postAction("/api/v1/inventories/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(7, $this->balance());
        $this->assertSame('225000.00', StockMovement::query()->where('type', 'write_off')->firstOrFail()->cost_total->toString());
        $this->postAction("/api/v1/inventories/{$id}/complete")->assertUnprocessable();
        $this->assertSame(7, $this->balance());
    }

    #[Test]
    public function an_incomplete_count_cannot_be_reviewed(): void
    {
        $id = $this->postAction('/api/v1/inventories', ['location_id' => $this->warehouse->id])->json('data.id');
        $this->postAction("/api/v1/inventories/{$id}/review")->assertUnprocessable();
    }

    #[Test]
    public function stock_changes_after_the_snapshot_require_a_new_count(): void
    {
        $id = $this->postAction('/api/v1/inventories', ['location_id' => $this->warehouse->id])->json('data.id');
        $this->putJson("/api/v1/inventories/{$id}/counts", ['items' => [
            ['variant_id' => $this->variant->id, 'actual_qty' => 10],
        ]])->assertOk();
        $this->postAction("/api/v1/inventories/{$id}/review")->assertOk();
        $this->stockUp(2);
        $this->postAction("/api/v1/inventories/{$id}/complete")->assertUnprocessable();
        $this->assertSame(12, $this->balance());
    }

    #[Test]
    public function a_foreign_branch_cannot_open_or_read_the_count(): void
    {
        $id = $this->postAction('/api/v1/inventories', ['location_id' => $this->warehouse->id])->json('data.id');
        $this->actingAsEmployee(Role::Warehouse, Branch::factory()->create());
        $this->getJson("/api/v1/inventories/{$id}")->assertNotFound();
        $this->postAction('/api/v1/inventories', ['location_id' => $this->warehouse->id])->assertNotFound();
    }
}
