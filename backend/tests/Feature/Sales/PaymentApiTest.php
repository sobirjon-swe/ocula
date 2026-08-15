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
use App\Modules\Sales\Models\Payment;
use App\Support\Exceptions\ImmutableRecordException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * To'lovlar — PROJECT.md 7.8-A, 7.21, ENUMS.md §4.
 *
 * Asosiy kafolatlar: to'lov holati `unpaid → partial → paid` bo'ylab
 * yuradi; **faqat naqd** kassa daftariga tushadi; storno to'lovni ham,
 * kassa yozuvini ham teskari qiladi.
 */
final class PaymentApiTest extends TestCase
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
    public function a_partial_payment_leaves_the_order_partly_paid(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $this->postAction("/api/v1/orders/{$id}/payments", [
            'amount' => '200000',
            'method' => 'cash',
        ])->assertCreated()->assertJsonPath('data.amount', '200000.00');

        $order = Order::findOrFail($id);
        $this->assertSame(PaymentStatus::Partial, $order->payment_status);
        $this->assertSame('200000.00', $order->paid->toString());
        $this->assertSame('300000.00', $order->debt->toString());
    }

    /**
     * Tovar ham berilgan, pul ham to'liq tushgan — chek yopiladi
     * (ENUMS.md §4: `delivered → closed`).
     */
    #[Test]
    public function paying_the_rest_closes_the_delivered_order(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '200000', 'method' => 'cash']);
        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '300000', 'method' => 'cash'])
            ->assertCreated();

        $order = Order::findOrFail($id);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Closed, $order->status);
        $this->assertSame('0.00', $order->debt->toString());
    }

    #[Test]
    public function a_cash_payment_lands_in_the_cash_book(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '500000', 'method' => 'cash'])
            ->assertCreated();

        $movement = CashMovement::query()->firstOrFail();
        $this->assertSame(CashDirection::In, $movement->type);
        $this->assertSame(CashCategory::Sale, $movement->category);
        $this->assertSame('500000.00', $movement->amount->toString());
        $this->assertSame(Payment::class, $movement->source_type);
    }

    /**
     * Karta terminal hisobiga tushadi, seyfga emas — kassa daftarida
     * uning o'rni yo'q (ENUMS.md §4). Aks holda smena yopilishida har
     * kuni kamomad ko'rinardi.
     */
    #[Test]
    public function a_card_payment_does_not_touch_the_cash_book(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '500000', 'method' => 'card'])
            ->assertCreated();

        $this->assertSame(0, CashMovement::count());
        $this->assertSame(PaymentStatus::Paid, Order::findOrFail($id)->payment_status);
    }

    #[Test]
    public function a_payment_can_not_exceed_the_balance_due(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '500001', 'method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertSame(0, Payment::count());
    }

    /**
     * Storno to'lovni ham, kassa yozuvini ham teskari qiladi — pul
     * daftarda qolib, buyurtmada yo'q bo'lib ketmasligi kerak.
     */
    #[Test]
    public function reversing_a_cash_payment_undoes_both_sides(): void
    {
        $this->actingAsDirector();
        $id = $this->sell();

        $paymentId = $this->postAction("/api/v1/orders/{$id}/payments", [
            'amount' => '500000',
            'method' => 'cash',
        ])->json('data.id');

        $this->postAction("/api/v1/payments/{$paymentId}/reverse", ['reason' => 'Kassir xato kiritdi'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '-500000.00')
            ->assertJsonPath('data.reverses_id', $paymentId);

        $order = Order::findOrFail($id);
        $this->assertSame('0.00', $order->paid->toString());
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);

        // Kassada ikkita yozuv: kirim va uni teskari qilgan tuzatish.
        $this->assertSame(2, CashMovement::count());

        $correction = CashMovement::query()->whereNotNull('reverses_id')->firstOrFail();
        $this->assertSame(CashDirection::Out, $correction->type);
        $this->assertSame(CashCategory::Correction, $correction->category);
    }

    #[Test]
    public function a_payment_can_not_be_reversed_twice(): void
    {
        $this->actingAsDirector();
        $id = $this->sell();

        $paymentId = $this->postAction("/api/v1/orders/{$id}/payments", [
            'amount' => '100000',
            'method' => 'cash',
        ])->json('data.id');

        $this->postAction("/api/v1/payments/{$paymentId}/reverse", ['reason' => 'Xato'])->assertCreated();
        $this->postAction("/api/v1/payments/{$paymentId}/reverse", ['reason' => 'Yana xato'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment');
    }

    /**
     * Kassir o'z xatosini o'zi yashira olmasligi kerak — storno faqat
     * direktorda (PERMISSIONS.md §4).
     */
    #[Test]
    public function a_seller_may_not_reverse_a_payment(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $paymentId = $this->postAction("/api/v1/orders/{$id}/payments", [
            'amount' => '100000',
            'method' => 'cash',
        ])->json('data.id');

        $this->postAction("/api/v1/payments/{$paymentId}/reverse", ['reason' => 'Xato'])
            ->assertForbidden();
    }

    /**
     * To'lov insert-only (7.21) — model darajasida ham to'siq bor.
     */
    #[Test]
    public function a_payment_row_can_not_be_updated(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->sell();

        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '100000', 'method' => 'cash']);

        $this->expectException(ImmutableRecordException::class);

        Payment::firstOrFail()->update(['amount' => '1.00']);
    }

    #[Test]
    public function a_cancelled_order_does_not_accept_payments(): void
    {
        $this->actingAsEmployee(Role::BranchManager, $this->branch);

        $id = $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'type' => 'order',
        ])->json('data.id');

        $this->postAction("/api/v1/orders/{$id}/cancel")->assertOk();

        $this->postAction("/api/v1/orders/{$id}/payments", ['amount' => '100000', 'method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order');
    }

    /**
     * Tez savdo cheki: 2 dona × 250 000 = 500 000.
     */
    private function sell(): int
    {
        return (int) $this->postAction('/api/v1/orders', $this->quickSalePayload())
            ->assertCreated()
            ->json('data.id');
    }
}
