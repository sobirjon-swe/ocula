<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Enums\CostSource;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * Chek va buyurtma — PROJECT.md 7.3, 7.8, 7.20.
 *
 * Asosiy kafolatlar: tovar **topshirilganda** ombordan chiqadi va
 * tannarx FIFO dan satrga yoziladi; qoldiq yetmasa savdo umuman
 * bo'lmaydi; chegirma limitdan oshsa direktor tasdig'i kerak.
 */
final class OrderApiTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->setUpSalesFixtures();
    }

    #[Test]
    public function a_quick_sale_leaves_the_warehouse_and_records_the_fifo_cost(): void
    {
        $this->stockUp(10, '75000');
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $response = $this->postAction('/api/v1/orders', $this->quickSalePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Delivered->value)
            ->assertJsonPath('data.total', '500000.00');

        // Raqam filial kodidan hosil bo'ladi (ANALIZ 3.12).
        $this->assertStringStartsWith('A-', (string) $response->json('data.number'));

        $this->assertSame(8, $this->balance());

        $item = OrderItem::firstOrFail();
        $this->assertSame('150000.00', $item->cost_total->toString());
        $this->assertSame(CostSource::Fifo, $item->cost_source);
        $this->assertNotNull($item->movement_id);

        // Chiqim chekka bog'langan — hisobotda "qaysi sotuv" ko'rinsin.
        $movement = StockMovement::query()
            ->where('type', MovementType::Sale->value)
            ->firstOrFail();

        $this->assertSame(-2, $movement->quantity);
        $this->assertSame(Order::class, $movement->source_type);
        $this->assertSame((int) $response->json('data.id'), $movement->source_id);
    }

    /**
     * Daromad topshirilgan paytda tan olinadi (7.8), yaratilgan paytda
     * emas — foyda hisoboti shu ustun bo'yicha ketadi.
     */
    #[Test]
    public function a_quick_sale_recognises_revenue_immediately(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload())->assertCreated();

        $order = Order::firstOrFail();
        $this->assertNotNull($order->revenue_recognized_at);
        $this->assertNotNull($order->delivered_at);
    }

    /**
     * Buyurtma esa yaratilganda omborga tegmaydi — linza hali yasalmagan.
     */
    #[Test]
    public function an_order_does_not_touch_stock_until_it_is_delivered(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $id = $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::New->value)
            ->json('data.id');

        $this->assertSame(5, $this->balance());
        $this->assertNull(Order::findOrFail((int) $id)->revenue_recognized_at);

        $this->postAction("/api/v1/orders/{$id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Delivered->value);

        $this->assertSame(3, $this->balance());
        $this->assertNotNull(Order::findOrFail((int) $id)->revenue_recognized_at);
    }

    #[Test]
    public function a_sale_is_refused_when_the_stock_is_not_enough(): void
    {
        $this->stockUp(1);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');

        // Tranzaksiya orqaga qaytgan — yarim chek qolmagan.
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $this->balance());
    }

    #[Test]
    public function a_variant_without_a_price_can_not_be_sold(): void
    {
        $this->stockUp(5);
        $this->variant->prices()->delete();

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    /**
     * Xizmat ombordan o'tmaydi va tannarxsiz qoladi — hisobotda u
     * alohida qatorda ko'rinishi uchun `cost_source = none` (3.9).
     */
    #[Test]
    public function a_service_line_has_no_cost_and_no_stock_movement(): void
    {
        $service = $this->makeService('50000');
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload([
            ['kind' => 'service', 'id' => $service->id, 'quantity' => 1],
        ]))->assertCreated()->assertJsonPath('data.total', '50000.00');

        $item = OrderItem::firstOrFail();
        $this->assertSame(CostSource::None, $item->cost_source);
        $this->assertSame(0, StockMovement::count());
    }

    /**
     * Individual linza ombordan o'tmaydi, lekin tannarxi bor (3.9) —
     * qo'lda kiritiladi, aks holda foyda soxta yuqori chiqardi.
     */
    #[Test]
    public function a_custom_lens_keeps_a_manual_cost(): void
    {
        $service = $this->makeService('400000');
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload([
            [
                'kind' => 'service',
                'id' => $service->id,
                'quantity' => 1,
                'cost_total' => '180000',
                'custom_lens_params' => ['sph' => '-2.25', 'cyl' => '-0.75'],
            ],
        ]))->assertCreated();

        $item = OrderItem::firstOrFail();
        $this->assertSame(CostSource::Manual, $item->cost_source);
        $this->assertSame('180000.00', $item->cost_total->toString());
        $this->assertSame('-2.25', $item->custom_lens_params['sph'] ?? null);
    }

    /**
     * Yakuniy summa 100 so'mga yaxlitlanadi, farq `rounding` ga (§15 #19).
     */
    #[Test]
    public function the_final_total_is_rounded_to_the_configured_step(): void
    {
        $this->variant->prices()->delete();
        $this->setPrice('250049');
        $this->stockUp(5);

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload(quantity: 1))
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '250049.00')
            ->assertJsonPath('data.total', '250000.00')
            ->assertJsonPath('data.rounding', '-49.00');
    }

    #[Test]
    public function a_seller_may_give_a_discount_up_to_the_limit(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        // 500 000 dan 50 000 — aynan 10% limit.
        $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'discount' => '50000',
        ])->assertCreated()
            ->assertJsonPath('data.discount', '50000.00')
            ->assertJsonPath('data.total', '450000.00')
            ->assertJsonPath('data.discount_approved_by', null);
    }

    #[Test]
    public function a_discount_above_the_limit_needs_the_director(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'discount' => '60000',
        ])->assertStatus(422)->assertJsonValidationErrors('discount');

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function the_director_may_approve_a_discount_above_the_limit(): void
    {
        $this->stockUp(5);
        $director = $this->actingAsDirector();

        $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'discount' => '60000',
        ])->assertCreated()
            ->assertJsonPath('data.total', '440000.00')
            ->assertJsonPath('data.discount_approved_by', $director->id);
    }

    #[Test]
    public function the_status_axis_moves_one_step_at_a_time(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $id = $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->json('data.id');

        $this->postAction("/api/v1/orders/{$id}/status", ['status' => 'materials_reserved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'materials_reserved');

        // `materials_reserved` dan to'g'ridan-to'g'ri `ready` ga sakrab
        // bo'lmaydi — ustaxona bosqichi o'tkazib yuborilardi.
        $this->postAction("/api/v1/orders/{$id}/status", ['status' => 'ready'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * Topshirish holat o'qidan qo'lda qo'yilmaydi: u ombor va daromadga
     * tegadi, shuning uchun faqat o'z endpoint'i orqali.
     */
    #[Test]
    public function delivered_can_not_be_set_through_the_status_endpoint(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $id = $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->json('data.id');

        $this->postAction("/api/v1/orders/{$id}/status", ['status' => 'delivered'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(5, $this->balance());
    }

    #[Test]
    public function an_undelivered_order_can_be_cancelled(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $id = $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->json('data.id');

        $this->postAction("/api/v1/orders/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(5, $this->balance());
    }

    #[Test]
    public function a_delivered_order_can_not_be_cancelled(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $id = $this->postAction('/api/v1/orders', $this->quickSalePayload())->json('data.id');

        $this->postAction("/api/v1/orders/{$id}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function a_doctor_may_not_create_an_order(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload())->assertForbidden();
    }

    #[Test]
    public function a_seller_may_not_sell_into_a_foreign_branch(): void
    {
        $this->stockUp(5);
        $other = Branch::factory()->create();

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'branch_id' => $other->id,
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function the_list_is_limited_to_the_own_branch(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $mine = $this->postAction('/api/v1/orders', $this->quickSalePayload())->json('data.id');

        $other = Branch::factory()->create();
        Order::factory()->for($other)->create();

        $response = $this->getJson('/api/v1/orders')->assertOk();

        $this->assertSame([$mine], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function creating_an_order_requires_an_idempotency_key(): void
    {
        $this->stockUp(5);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postJson('/api/v1/orders', $this->quickSalePayload())->assertStatus(400);
    }

    /**
     * Filialda internet uzilib qayta yuborilganda ikkinchi chek
     * yaratilmaydi va ombor ikki marta kamaymaydi (§9).
     */
    #[Test]
    public function repeating_the_same_key_does_not_create_a_second_order(): void
    {
        $this->stockUp(10);
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $key = (string) Str::uuid();
        $payload = $this->quickSalePayload();

        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/orders', $payload)->assertCreated();
        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/orders', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $this->balance());
    }
}
