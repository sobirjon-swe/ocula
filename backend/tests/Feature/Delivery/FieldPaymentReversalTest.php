<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Actions\Collection\CollectDriverCash;
use App\Modules\Delivery\Services\DriverCashLedger;
use App\Modules\Sales\Actions\Payment\RecordPayment;
use App\Modules\Sales\Actions\Payment\ReversePayment;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FieldPaymentReversalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reversing_uncollected_field_cash_removes_driver_holding_and_restores_customer_debt(): void
    {
        [$driver, $order, $payment] = $this->receipt();
        app(ReversePayment::class)->handle(User::factory()->create(), $payment, 'Field receipt entered in error');
        $this->assertSame('0.00', app(DriverCashLedger::class)->balanceOf($driver->id)->toString());
        $this->assertSame('100000.00', $order->fresh()->debt->toString());
        $this->assertSame($driver->id, Payment::where('reverses_id', $payment->id)->firstOrFail()->collected_by);
    }

    #[Test]
    public function cash_already_partly_collected_requires_an_audited_refund_instead_of_receipt_storno(): void
    {
        [$driver, $order, $payment] = $this->receipt();
        app(CollectDriverCash::class)->handle(User::factory()->create(), $driver, $order->branch_id, Money::of('50000'));
        try {
            app(ReversePayment::class)->handle(User::factory()->create(), $payment, 'Cannot identify which collected cash this refunds');
            $this->fail('Partly collected field receipts require a separate audited refund.');
        } catch (ValidationException) {
            $this->assertSame('50000.00', app(DriverCashLedger::class)->balanceOf($driver->id)->toString());
            $this->assertSame(0, Payment::where('reverses_id', $payment->id)->count());
        }
    }

    /** @return array{User, Order, Payment} */
    private function receipt(): array
    {
        $branch = Branch::factory()->create();
        $driver = User::factory()->for($branch)->create();
        $order = Order::factory()->delivered()->for($branch)->create(['customer_id' => Customer::factory()->create()->id, 'total' => '100000', 'debt' => '100000']);
        $payment = app(RecordPayment::class)->handle($driver, $order, Money::of('100000'), PaymentMethod::Cash, collectedInField: true);
        app(DriverCashLedger::class)->credit($driver->id, Money::of('100000'));

        return [$driver, $order, $payment];
    }
}
