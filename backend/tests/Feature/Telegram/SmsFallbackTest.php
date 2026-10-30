<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Actions\SendTelegramNotification;
use App\Modules\Telegram\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SmsFallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_without_telegram_receives_a_configured_sms_fallback_once(): void
    {
        config(['services.sms.enabled' => true, 'services.sms.url' => 'https://sms.test/send', 'services.sms.username' => 'test', 'services.sms.password' => 'test', 'services.sms.originator' => 'Optika']);
        Http::fake(['*' => Http::response(['status' => 'accepted'], 200)]);
        $customer = Customer::factory()->create(['telegram_id' => null]);
        $sender = app(SendTelegramNotification::class);
        $sender->handle($customer, NotificationType::OrderReady, 'Buyurtma tayyor', dedupeKey: 'ready:one');
        $sender->handle($customer, NotificationType::OrderReady, 'Buyurtma tayyor', dedupeKey: 'ready:one');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://sms.test/send' && $r['messages'][0]['recipient'] === ltrim($customer->phone, '+'));
        $this->assertDatabaseHas('sms_notifications', ['customer_id' => $customer->id, 'status' => 'sent', 'dedupe_key' => 'ready:one']);
    }
}
