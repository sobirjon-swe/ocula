<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Telegram\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Qarz eslatmasi buyrug'i — PROJECT.md 7.6, BOSQICH-7.md §4.2.
 *
 * Asosiy kafolatlar: eslatma kunlariga mos buyurtmaga xabar ketadi; mos
 * kelmagan kunga ketmaydi; ikkinchi ishga tushirishda dublikat ketmaydi.
 */
final class DebtReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->main()->create(['code' => 'A']);
        $this->author = User::factory()->for($this->branch)->create();
    }

    #[Test]
    public function it_reminds_a_customer_whose_due_date_matches_a_configured_offset(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $order = $this->orderWithDebt($customer, dueDate: now()->toDateString());

        $this->artisan('telegram:remind-debts')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 42);

        $this->assertSame(
            1,
            TelegramNotification::query()->where('source_id', $order->id)->count(),
        );
    }

    #[Test]
    public function it_skips_a_due_date_that_matches_no_configured_offset(): void
    {
        Http::fake();

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $this->orderWithDebt($customer, dueDate: now()->addDays(20)->toDateString());

        $this->artisan('telegram:remind-debts')->assertExitCode(0);

        Http::assertNothingSent();
    }

    #[Test]
    public function running_it_twice_does_not_send_a_duplicate(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $this->orderWithDebt($customer, dueDate: now()->toDateString());

        $this->artisan('telegram:remind-debts')->assertExitCode(0);
        $this->artisan('telegram:remind-debts')->assertExitCode(0);

        Http::assertSentCount(1);
        $this->assertSame(1, TelegramNotification::query()->count());
    }

    private function orderWithDebt(Customer $customer, string $dueDate): Order
    {
        return Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'created_by' => $this->author->id,
            'debt' => '150000',
            'due_date' => $dueDate,
        ]);
    }
}
