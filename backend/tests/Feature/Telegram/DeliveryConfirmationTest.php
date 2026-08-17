<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Events\StopDelivered;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Yetkazish tasdig'i — PROJECT.md 7.4, BOSQICH-8.md §4, §5 #6.
 *
 * Asosiy kafolatlar: yetkazilgach mijozga tugmali xabar ketadi;
 * "Ha"/"Yo'q" callback holatni yangilaydi; muddat o'tgach `unconfirmed`
 * bo'ladi.
 */
final class DeliveryConfirmationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_delivered_stop_sends_an_inline_keyboard_to_a_linked_customer(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $stop = $this->makeStop($customer);

        event(new StopDelivered($stop));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $markup = $request['reply_markup']['inline_keyboard'][0] ?? [];

            return $request['chat_id'] === 42 && count($markup) === 2;
        });
    }

    #[Test]
    public function the_confirm_callback_marks_the_stop_confirmed(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $stop = $this->makeStop($customer, ConfirmationStatus::Awaiting);

        $this->postJson('/api/v1/telegram/webhook', [
            'callback_query' => [
                'id' => 'cb1',
                'data' => "delivery:confirm:{$stop->id}",
                'from' => ['id' => 42],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertOk();

        $this->assertSame(ConfirmationStatus::Confirmed, $stop->fresh()->confirmation_status);
        $this->assertNotNull($stop->fresh()->customer_confirmed_at);
    }

    #[Test]
    public function the_dispute_callback_marks_the_stop_disputed(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        $stop = $this->makeStop($customer, ConfirmationStatus::Awaiting);

        $this->postJson('/api/v1/telegram/webhook', [
            'callback_query' => [
                'id' => 'cb2',
                'data' => "delivery:dispute:{$stop->id}",
                'from' => ['id' => 42],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertOk();

        $this->assertSame(ConfirmationStatus::Disputed, $stop->fresh()->confirmation_status);
    }

    #[Test]
    public function an_unanswered_confirmation_expires_after_the_configured_hours(): void
    {
        Setting::put(SettingKey::DeliveryAutoConfirmAfterHours, 24);

        $customer = Customer::factory()->create();
        $stop = $this->makeStop($customer, ConfirmationStatus::Awaiting);
        $stop->update(['delivered_at' => now()->subHours(25)]);

        $this->artisan('delivery:expire-confirmations')->assertExitCode(0);

        $this->assertSame(ConfirmationStatus::Unconfirmed, $stop->fresh()->confirmation_status);
    }

    #[Test]
    public function a_confirmation_within_the_window_is_left_untouched(): void
    {
        Setting::put(SettingKey::DeliveryAutoConfirmAfterHours, 24);

        $customer = Customer::factory()->create();
        $stop = $this->makeStop($customer, ConfirmationStatus::Awaiting);
        $stop->update(['delivered_at' => now()->subHours(2)]);

        $this->artisan('delivery:expire-confirmations')->assertExitCode(0);

        $this->assertSame(ConfirmationStatus::Awaiting, $stop->fresh()->confirmation_status);
    }

    private function makeStop(Customer $customer, ?ConfirmationStatus $confirmationStatus = null): TripStop
    {
        $trip = Trip::factory()->create();

        return TripStop::factory()->create([
            'trip_id' => $trip->id,
            'type' => TripStopType::Customer,
            'customer_id' => $customer->id,
            'status' => TripStopStatus::Delivered,
            'delivered_at' => now(),
            'confirmation_status' => $confirmationStatus,
        ]);
    }
}
