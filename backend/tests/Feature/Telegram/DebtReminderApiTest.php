<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Qarz eslatmasini qo'lda yuborish — BOSQICH-7.md §4.2, §5 #3.
 *
 * `finance.debt.remind` ruxsati director va accountant'da; sotuvchida yo'q.
 */
final class DebtReminderApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function an_accountant_can_send_a_manual_debt_reminder(): void
    {
        $this->seedPermissions();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $branch = Branch::factory()->main()->create(['code' => 'A']);
        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'debt' => '150000',
            'due_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee(Role::Accountant);
        $this->postJson("/api/v1/orders/{$order->id}/debt-reminder")
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent');
    }

    #[Test]
    public function a_seller_cannot_send_a_manual_debt_reminder(): void
    {
        $this->seedPermissions();

        $branch = Branch::factory()->main()->create(['code' => 'A']);
        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'debt' => '150000',
            'due_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee(Role::Seller, $branch);
        $this->postJson("/api/v1/orders/{$order->id}/debt-reminder")->assertForbidden();
    }
}
