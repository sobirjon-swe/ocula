<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Warehouse\Actions\Defect\ReportDefect;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

final class SupplierDefectReturnTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    #[Test]
    public function supplier_defect_prepares_an_act_from_fifo_provenance_without_a_second_stock_issue(): void
    {
        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->actingAsDirector();
        $purchase = Purchase::factory()->create(['branch_id' => $this->branch->id, 'location_id' => $this->warehouse->id]);
        app(StockLedger::class)->receive($this->author, $this->warehouse, $this->variant->id, 5, Money::of('75000'), document: $purchase);
        $defect = app(ReportDefect::class)->handle($this->author, $this->warehouse, $this->variant->id, 2, DefectReason::SupplierDefect, 'Zavod braki');
        $act = DB::table('supplier_returns')->where('defect_id', $defect->id)->first();
        $this->assertNotNull($act);
        $this->assertSame($purchase->supplier_id, $act->supplier_id);
        $this->assertSame('150000.00', $act->amount);
        $this->withHeader('Idempotency-Key', 'return-a')->postJson("/api/v1/supplier-returns/{$act->id}/post", ['purchase_id' => $purchase->id])->assertOk();
        $this->assertDatabaseHas('supplier_transactions', ['supplier_return_id' => $act->id, 'amount' => '150000.00', 'type' => 'return']);
        $this->assertSame(3, $this->balance());
        $this->withHeader('Idempotency-Key', 'return-b')->postJson("/api/v1/supplier-returns/{$act->id}/post", ['purchase_id' => $purchase->id])->assertUnprocessable();
    }
}
