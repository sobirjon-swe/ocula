<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Core\Enums\Role;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Enums\CashDirection;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Models\OrderReturn;
use App\Modules\Sales\Models\OrderReturnItem;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\StockLayer;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * Qaytarish — SCHEMA.md §4 (3.6), PROJECT.md 7.20, 7.21.
 *
 * Asosiy kafolatlar: `restock` tovarni omborga **sotilgandagi tannarx**
 * bilan qaytaradi; pul manfiy to'lov va `refund` kassa chiqimi bo'lib
 * chiqadi; to'liq qaytarilganda ikkala holat o'qi ham yopiladi.
 */
final class ReturnApiTest extends TestCase
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
    public function a_restocked_line_returns_to_the_warehouse_at_the_sold_cost(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->assertSame(8, $this->balance());

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'customer_changed_mind',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1, 'restock' => true]],
            'refund' => '250000',
        ])->assertCreated()->assertJsonPath('data.amount', '250000.00');

        $this->assertSame(9, $this->balance());

        $movement = StockMovement::query()
            ->where('type', MovementType::Return->value)
            ->firstOrFail();

        $this->assertSame(1, $movement->quantity);
        $this->assertSame(OrderReturn::class, $movement->source_type);

        // Yangi qatlam **sotilgandagi** tannarx bilan ochiladi, bugungi
        // narx bilan emas — aks holda qaytarish foyda ko'rsatib qo'yardi.
        $layer = StockLayer::query()->where('movement_id', $movement->id)->firstOrFail();
        $this->assertSame('75000.00', $layer->unit_cost->toString());
    }

    #[Test]
    public function the_refund_leaves_the_cash_book_and_the_order_balance(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'product_defect',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
            'refund' => '250000',
        ])->assertCreated();

        $refund = CashMovement::query()->where('category', CashCategory::Refund->value)->firstOrFail();
        $this->assertSame(CashDirection::Out, $refund->type);
        $this->assertSame('250000.00', $refund->amount->toString());

        // Buyurtma bo'yicha to'langan summa kamaydi (manfiy to'lov).
        $this->assertSame('250000.00', Order::findOrFail($orderId)->paid->toString());
    }

    /**
     * Brak omborga qaytmaydi va `defects` ga yoziladi (7.5). Ombor
     * harakati yozilmaydi: tovar sotilganda allaqachon chiqib bo'lgan,
     * uni yana chiqarish qoldiqni ikki marta kamaytirardi.
     */
    #[Test]
    public function a_broken_item_does_not_go_back_to_stock_but_is_recorded_as_a_defect(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'product_defect',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1, 'restock' => false]],
        ])->assertCreated();

        $this->assertSame(8, $this->balance());
        $this->assertSame(0, StockMovement::query()->where('type', MovementType::Return->value)->count());

        $line = OrderReturnItem::firstOrFail();
        $this->assertFalse($line->restock);
        $this->assertNull($line->movement_id);

        $defect = Defect::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(DefectReason::SupplierDefect, $defect->reason);
        $this->assertSame((int) $orderId, $defect->order_id);
        $this->assertSame('75000.00', $defect->cost_impact->toString());
        $this->assertNull($defect->movement_id);
    }

    /**
     * Sabab qaytarish sababidan kelib chiqadi: nuqsonsiz tovarni
     * mijoz qaytarsa, u yetkazib beruvchining aybi emas.
     */
    #[Test]
    public function a_non_defective_return_is_recorded_on_the_customer_side(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'customer_changed_mind',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1, 'restock' => false]],
        ])->assertCreated();

        $this->assertSame(
            DefectReason::CustomerRequest,
            Defect::query()->withoutGlobalScopes()->firstOrFail()->reason,
        );
    }

    #[Test]
    public function returning_everything_closes_both_axes(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'wrong_item',
            'items' => [['order_item_id' => $itemId, 'quantity' => 2]],
            'refund' => '500000',
        ])->assertCreated();

        $order = Order::findOrFail($orderId);
        $this->assertSame(OrderStatus::Returned, $order->status);
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(10, $this->balance());
    }

    /**
     * Qisman qaytarishda buyurtma o'z holatida qoladi — mijozda hali
     * tovar bor.
     */
    #[Test]
    public function a_partial_return_leaves_the_order_open(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'wrong_item',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
            'refund' => '250000',
        ])->assertCreated();

        $order = Order::findOrFail($orderId);
        $this->assertSame(OrderStatus::Closed, $order->status);
        $this->assertSame(PaymentStatus::Partial, $order->payment_status);
    }

    /**
     * Bir dona qaytarilgandan keyin ikkinchi hujjatda yana ikkitasini
     * qaytarib bo'lmaydi — sotilganidan ko'p qaytsa, ombor va foyda
     * o'ylab topilgan tovar hisobiga o'sib ketardi.
     */
    #[Test]
    public function more_than_what_was_sold_can_not_be_returned(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'other',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'other',
            'items' => [['order_item_id' => $itemId, 'quantity' => 2]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(9, $this->balance());
        $this->assertDatabaseCount('returns', 1);
    }

    #[Test]
    public function the_refund_can_not_exceed_what_was_paid(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay('200000');

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'other',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
            'refund' => '250000',
        ])->assertStatus(422)->assertJsonValidationErrors('refund');

        $this->assertDatabaseCount('returns', 0);
    }

    /**
     * Topshirilmagan buyurtma qaytarilmaydi — u bekor qilinadi, ombor
     * hali tegmagan.
     */
    #[Test]
    public function an_undelivered_order_can_not_be_returned(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $orderId = $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->json('data.id');

        $itemId = OrderItem::firstOrFail()->id;

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'other',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('order');
    }

    #[Test]
    public function a_doctor_may_not_create_a_return(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        [$orderId, $itemId] = $this->sellAndPay();

        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction("/api/v1/orders/{$orderId}/returns", [
            'reason' => 'other',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertForbidden();
    }

    /**
     * Tez savdo: 2 dona × 250 000, naqd to'langan.
     *
     * @return array{0: int, 1: int}
     */
    private function sellAndPay(string $paid = '500000'): array
    {
        $orderId = (int) $this->postAction('/api/v1/orders', $this->quickSalePayload())
            ->assertCreated()
            ->json('data.id');

        $this->postAction("/api/v1/orders/{$orderId}/payments", [
            'amount' => $paid,
            'method' => 'cash',
        ])->assertCreated();

        return [$orderId, OrderItem::query()->where('order_id', $orderId)->firstOrFail()->id];
    }
}
