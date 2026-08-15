<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use App\Modules\Telegram\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Buyurtma tayyor bo'lganda Telegram xabari — BOSQICH-7.md §4.1.
 *
 * Asosiy kafolatlar: bog'langan mijozga xabar ketadi; bog'lanmagan
 * mijozda haqiqiy HTTP so'rov umuman yuborilmaydi; `rework`dan qayta
 * `ready`ga qaytilganda xabar yana ketadi.
 */
final class OrderReadyNotificationTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->main()->create(['code' => 'A']);
    }

    #[Test]
    public function moving_an_order_to_ready_notifies_a_linked_customer(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $customer = Customer::factory()->create(['telegram_id' => 555111]);
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InWorkshop,
        ]);

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->changeStatus($order->id, 'ready')->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 555111);

        $notification = TelegramNotification::query()->firstOrFail();
        $this->assertSame(NotificationType::OrderReady, $notification->type);
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame($order->id, $notification->source_id);
    }

    #[Test]
    public function an_unlinked_customer_never_triggers_an_http_request(): void
    {
        Http::fake();

        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InWorkshop,
        ]);

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->changeStatus($order->id, 'ready')->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, TelegramNotification::query()->count());
    }

    #[Test]
    public function becoming_ready_again_after_rework_sends_a_fresh_notification(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 555222]);
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => OrderStatus::InWorkshop,
        ]);

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->changeStatus($order->id, 'ready')->assertOk();
        $this->changeStatus($order->id, 'rework')->assertOk();
        $this->changeStatus($order->id, 'ready')->assertOk();

        $this->assertSame(2, TelegramNotification::query()->count());
        Http::assertSentCount(2);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function changeStatus(int $orderId, string $status): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/orders/{$orderId}/status", ['status' => $status]);
    }
}
