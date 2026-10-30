<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class StandaloneDefectApiTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10, '75000');
    }

    #[Test]
    public function a_standalone_defect_issues_stock_once_and_is_hidden_from_foreign_branches(): void
    {
        $this->actingAsEmployee(Role::Warehouse, $this->branch);
        $payload = ['location_id' => $this->warehouse->id, 'variant_id' => $this->variant->id, 'quantity' => 2, 'reason' => 'transport_damage', 'note' => 'Broken during shelf handling'];
        $id = (int) $this->postAction('/api/v1/defects', $payload)->assertCreated()->assertJsonPath('data.quantity', 2)->json('data.id');
        $this->assertSame(8, $this->balance());
        $movement = StockMovement::query()->where('type', 'defect')->firstOrFail();
        $this->assertSame(-2, $movement->quantity);
        $this->assertSame('150000.00', $movement->cost_total->toString());
        $this->assertDatabaseHas('defects', ['id' => $id, 'movement_id' => $movement->id, 'branch_id' => $this->branch->id]);
        $this->actingAsEmployee(Role::Warehouse, Branch::factory()->create());
        $this->postAction('/api/v1/defects', $payload)->assertNotFound();
        $this->getJson("/api/v1/defects/$id")->assertNotFound();
        $this->assertSame(1, Defect::withoutGlobalScopes()->count());
        $this->assertSame(1, StockMovement::withoutGlobalScopes()->where('type', 'defect')->count());
    }

    #[Test]
    public function an_employee_without_defect_permission_cannot_change_stock(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $this->postAction('/api/v1/defects', ['location_id' => $this->warehouse->id, 'variant_id' => $this->variant->id, 'quantity' => 2, 'reason' => 'transport_damage'])->assertForbidden();
        $this->assertSame(10, $this->balance());
        $this->assertDatabaseCount('defects', 0);
    }
}
