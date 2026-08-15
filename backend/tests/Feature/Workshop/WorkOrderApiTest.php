<?php

declare(strict_types=1);

namespace Tests\Feature\Workshop;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\MasterStock;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * Ustaxona — PROJECT.md §6.6, 7.5, ANALIZ 3.4.
 *
 * Asosiy kafolatlar: ish buyrug'i tizim tomonidan tug'iladi; sarflangan
 * material topshirishda **ikkinchi marta** ombordan chiqmaydi;
 * `customer_request` braki buyurtmani qayta ishlashga qaytaradi.
 */
final class WorkOrderApiTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10, '75000');

        $this->master = User::factory()->for($this->branch)->create(['name' => 'Usta Anvar']);
        $this->master->assignRole(Role::Master->value);
    }

    /**
     * Buyruq **qo'lda yaratilmaydi** — buyurtma ustaxonaga o'tganda
     * tizim uni o'zi ochadi (§6.6).
     */
    #[Test]
    public function moving_an_order_into_the_workshop_opens_a_work_order(): void
    {
        $orderId = $this->orderInWorkshop();

        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($orderId, $workOrder->order_id);
        $this->assertSame(WorkOrderStatus::Queued, $workOrder->status);
        $this->assertSame(1, $workOrder->items()->count());
        $this->assertSame(2, $workOrder->items()->firstOrFail()->quantity);
    }

    #[Test]
    public function a_second_pass_does_not_open_a_second_work_order(): void
    {
        $this->orderInWorkshop();

        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->count());
    }

    #[Test]
    public function the_master_takes_the_work_and_finishes_it(): void
    {
        $this->orderInWorkshop();
        $id = WorkOrder::withoutGlobalScopes()->firstOrFail()->id;

        $this->actingAs($this->master, 'sanctum');

        $this->postAction("/api/v1/work-orders/{$id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', WorkOrderStatus::InProgress->value)
            ->assertJsonPath('data.master_id', $this->master->id);

        $this->postAction("/api/v1/work-orders/{$id}/finish")
            ->assertOk()
            ->assertJsonPath('data.status', WorkOrderStatus::Done->value);
    }

    /**
     * Material `consume` bilan chiqadi — bu sotuv emas, lekin
     * tannarxga tushadi (ANALIZ 3.4).
     */
    #[Test]
    public function consuming_material_takes_it_out_of_the_master_reserve(): void
    {
        $this->orderInWorkshop();
        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();
        $item = $workOrder->items()->firstOrFail();

        $this->giveToMaster(2);

        $this->actingAs($this->master, 'sanctum');

        $this->postAction("/api/v1/work-orders/{$workOrder->id}/consume", [
            'item_id' => $item->id,
        ])->assertOk();

        $movement = StockMovement::query()->withoutGlobalScopes()
            ->where('type', MovementType::Consume->value)
            ->firstOrFail();

        $this->assertSame(-2, $movement->quantity);
        $this->assertSame('150000.00', $movement->cost_total->absolute()->toString());

        $this->assertTrue($item->fresh()?->isConsumed());
        $this->assertSame(0, $this->masterBalance());
    }

    /**
     * **Ikki marta chiqimning oldi olinadi.** Material ustaxonada
     * sarflangan bo'lsa, topshirishda u yana ombordan chiqarilmaydi.
     */
    #[Test]
    public function delivering_does_not_issue_material_that_was_already_consumed(): void
    {
        $orderId = $this->orderInWorkshop();
        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();
        $item = $workOrder->items()->firstOrFail();

        $this->giveToMaster(2);
        $this->assertSame(8, $this->balance());

        $this->actingAs($this->master, 'sanctum');
        $this->postAction("/api/v1/work-orders/{$workOrder->id}/consume", ['item_id' => $item->id])
            ->assertOk();

        // Buyurtmani topshiramiz.
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->postAction("/api/v1/orders/{$orderId}/status", ['status' => 'ready'])->assertOk();
        $this->postAction("/api/v1/orders/{$orderId}/deliver")->assertOk();

        // Ombor 8 da qoldi: 2 dona ustaga berilgan va o'sha yerdan
        // sarflangan, topshirish qayta chiqim yozmadi.
        $this->assertSame(8, $this->balance());
        $this->assertSame(0, $this->masterBalance());

        $this->assertSame(
            0,
            StockMovement::query()->withoutGlobalScopes()
                ->where('type', MovementType::Sale->value)->count(),
        );

        // Tannarx baribir buyurtmada — sarflash paytidagi FIFO qiymati.
        $this->assertSame('150000.00', Order::findOrFail($orderId)->cost_total->toString());
    }

    /**
     * `customer_request` braki buyurtmani qayta ishlashga qaytaradi
     * (7.5) — usta faqat sababni tanlaydi.
     */
    #[Test]
    public function a_customer_request_defect_sends_the_order_back_to_rework(): void
    {
        $orderId = $this->orderInWorkshop();
        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();
        $item = $workOrder->items()->firstOrFail();

        $this->actingAs($this->master, 'sanctum');

        $this->postAction("/api/v1/work-orders/{$workOrder->id}/defect", [
            'item_id' => $item->id,
            'reason' => DefectReason::CustomerRequest->value,
            'note' => 'Mijozga to\'g\'ri kelmadi',
        ])->assertCreated();

        $fresh = WorkOrder::withoutGlobalScopes()->findOrFail($workOrder->id);

        $this->assertSame(WorkOrderStatus::Queued, $fresh->status);
        $this->assertSame(1, $fresh->rework_count);
        $this->assertSame(OrderStatus::Rework, Order::findOrFail($orderId)->status);
    }

    /**
     * Usta xatosi buyurtmani qaytarmaydi — buyruq `defect` bo'lib
     * qoladi va zarar do'kon zimmasiga yoziladi (7.5, 7.12).
     */
    #[Test]
    public function a_master_error_writes_the_material_off_and_stops_the_work(): void
    {
        $this->orderInWorkshop();
        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();
        $item = $workOrder->items()->firstOrFail();

        $this->actingAs($this->master, 'sanctum');

        $this->postAction("/api/v1/work-orders/{$workOrder->id}/defect", [
            'item_id' => $item->id,
            'reason' => DefectReason::MasterError->value,
            'note' => 'Linzani sindirdim',
            'quantity' => 1,
        ])->assertCreated();

        $this->assertSame(WorkOrderStatus::Defect, WorkOrder::withoutGlobalScopes()
            ->findOrFail($workOrder->id)->status);

        // Sarflanmagan material ombordan chiqariladi.
        $this->assertSame(9, $this->balance());

        $defect = Defect::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(DefectReason::MasterError, $defect->reason);
        $this->assertSame('75000.00', $defect->cost_impact->toString());
        $this->assertNotNull($defect->movement_id);
    }

    /**
     * Sarflangan material daftardan allaqachon chiqqan — brak uni
     * ikkinchi marta chiqarmaydi, faqat zararni yozadi.
     */
    #[Test]
    public function a_defect_on_consumed_material_does_not_touch_stock_again(): void
    {
        $this->orderInWorkshop();
        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();
        $item = $workOrder->items()->firstOrFail();

        $this->giveToMaster(2);
        $this->actingAs($this->master, 'sanctum');
        $this->postAction("/api/v1/work-orders/{$workOrder->id}/consume", ['item_id' => $item->id])
            ->assertOk();

        $movementsBefore = StockMovement::query()->withoutGlobalScopes()->count();

        $this->postAction("/api/v1/work-orders/{$workOrder->id}/defect", [
            'item_id' => $item->id,
            'reason' => DefectReason::MasterError->value,
            'note' => 'Ishlov berishda sindi',
            'quantity' => 1,
        ])->assertCreated();

        $this->assertSame($movementsBefore, StockMovement::query()->withoutGlobalScopes()->count());

        $defect = Defect::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertNull($defect->movement_id);
        $this->assertSame('75000.00', $defect->cost_impact->toString());
    }

    #[Test]
    public function the_same_material_can_not_be_consumed_twice(): void
    {
        $this->orderInWorkshop();
        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();
        $item = $workOrder->items()->firstOrFail();

        $this->giveToMaster(4);
        $this->actingAs($this->master, 'sanctum');

        $this->postAction("/api/v1/work-orders/{$workOrder->id}/consume", ['item_id' => $item->id])
            ->assertOk();

        $this->postAction("/api/v1/work-orders/{$workOrder->id}/consume", ['item_id' => $item->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('item');
    }

    #[Test]
    public function the_board_puts_urgent_work_on_top(): void
    {
        $this->orderInWorkshop();
        $first = WorkOrder::withoutGlobalScopes()->firstOrFail();

        $second = WorkOrder::factory()->for($this->branch)->create([
            'order_id' => $first->order_id,
            'created_by' => $this->author->id,
        ]);

        // Muhimlikni ustaning o'zi qo'yadi — PERMISSIONS.md §6 da
        // `set_priority` filial boshlig'ida yo'q.
        $this->actingAs($this->master, 'sanctum');

        $this->putJson("/api/v1/work-orders/{$second->id}/priority", ['priority' => 'urgent'])
            ->assertOk();

        $board = $this->getJson('/api/v1/work-orders/board')->assertOk();

        $this->assertSame($second->id, $board->json('data.0.id'));
    }

    #[Test]
    public function a_seller_may_not_touch_the_workshop(): void
    {
        $this->orderInWorkshop();
        $id = WorkOrder::withoutGlobalScopes()->firstOrFail()->id;

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction("/api/v1/work-orders/{$id}/start")->assertForbidden();
    }

    /**
     * Buyurtmani ustaxonaga o'tkazadi va ish buyrug'i tug'ilishini
     * kutadi. Buyurtma turi `order` — tez savdoda ustaxona bo'lmaydi.
     */
    private function orderInWorkshop(): int
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $id = (int) $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->assertCreated()->json('data.id');

        $this->postAction("/api/v1/orders/{$id}/status", ['status' => 'materials_reserved'])->assertOk();
        $this->postAction("/api/v1/orders/{$id}/status", ['status' => 'in_workshop'])->assertOk();

        return $id;
    }

    private function giveToMaster(int $quantity): void
    {
        app(MasterStock::class)->issueTo(
            $this->author, $this->master, $this->warehouse, $this->variant->id, $quantity,
        );
    }

    private function masterBalance(): int
    {
        return app(MasterStock::class)->balanceOf($this->master, $this->branch->id, $this->variant->id);
    }
}
