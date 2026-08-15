<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Models\TelegramLinkCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Mijozni botga bog'lash — BOSQICH-7.md §3, §5 #5.
 *
 * Asosiy kafolatlar: to'g'ri kod mijozni bog'laydi va bir martalik
 * bo'lib qoladi; muddati o'tgan/noto'g'ri kod rad etiladi; noto'g'ri
 * webhook sekret 403 qaytaradi.
 */
final class TelegramLinkTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    #[Test]
    public function a_seller_can_generate_a_link_code_for_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAsEmployee(Role::Seller);
        $response = $this->postJson("/api/v1/customers/{$customer->id}/telegram-link-code")
            ->assertCreated();

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(1, TelegramLinkCode::query()->where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function a_valid_start_command_links_the_customer_and_burns_the_code(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create();
        $linkCode = TelegramLinkCode::create([
            'customer_id' => $customer->id,
            'code' => '123456',
            'expires_at' => now()->addMinutes(15),
            'created_by' => User::factory()->create()->id,
        ]);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['chat' => ['id' => 987654], 'text' => '/start 123456'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertOk();

        $this->assertSame(987654, $customer->fresh()->telegram_id);
        $this->assertNotNull($linkCode->fresh()->used_at);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 987654);
    }

    #[Test]
    public function an_expired_code_does_not_link_the_customer(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create();
        TelegramLinkCode::create([
            'customer_id' => $customer->id,
            'code' => '654321',
            'expires_at' => now()->subMinute(),
            'created_by' => User::factory()->create()->id,
        ]);

        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['chat' => ['id' => 111222], 'text' => '/start 654321'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertOk();

        $this->assertNull($customer->fresh()->telegram_id);
    }

    #[Test]
    public function a_wrong_webhook_secret_is_rejected(): void
    {
        $this->postJson('/api/v1/telegram/webhook', [
            'message' => ['chat' => ['id' => 1], 'text' => '/start whatever'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'not-the-secret'])->assertForbidden();
    }
}
