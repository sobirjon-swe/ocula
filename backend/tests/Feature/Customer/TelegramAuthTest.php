<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Sales\Models\Customer;
use Database\Seeders\CustomerPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Telegram `initData` orqali kirish — PROJECT.md §9, BOSQICH-9.md §3.
 *
 * Asosiy kafolatlar: to'g'ri imzo token beradi va yangi mijoz yaratadi;
 * noto'g'ri imzo 401; ikkinchi kirishda yangi mijoz yaratilmaydi.
 */
final class TelegramAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CustomerPermissionSeeder::class);
    }

    #[Test]
    public function a_valid_signature_logs_in_and_creates_a_new_customer(): void
    {
        $initData = $this->signedInitData(telegramId: 555111, firstName: 'Aziz', languageCode: 'ru');

        $response = $this->postJson('/api/v1/customer/auth/telegram', ['init_data' => $initData])
            ->assertOk();

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertSame('Aziz', $response->json('data.customer.name'));
        $this->assertSame('ru', $response->json('data.customer.locale'));

        $customer = Customer::query()->where('telegram_id', 555111)->firstOrFail();
        $this->assertTrue($customer->hasRole('customer'));

        $this->getJson('/api/v1/customer/profile', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);
    }

    #[Test]
    public function an_invalid_signature_is_rejected(): void
    {
        $initData = $this->signedInitData(telegramId: 555222).'&hash=deadbeef';

        $this->postJson('/api/v1/customer/auth/telegram', ['init_data' => $initData])
            ->assertUnauthorized();
    }

    #[Test]
    public function a_second_login_reuses_the_same_customer(): void
    {
        $initData = $this->signedInitData(telegramId: 555333);

        $this->postJson('/api/v1/customer/auth/telegram', ['init_data' => $initData])->assertOk();
        $this->postJson('/api/v1/customer/auth/telegram', ['init_data' => $initData])->assertOk();

        $this->assertSame(1, Customer::query()->where('telegram_id', 555333)->count());
    }

    private function signedInitData(int $telegramId, string $firstName = 'Test', string $languageCode = 'uz'): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'query_id' => 'AAEfake',
            'user' => json_encode([
                'id' => $telegramId,
                'first_name' => $firstName,
                'language_code' => $languageCode,
            ]),
        ];

        ksort($fields);

        $dataCheckString = collect($fields)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', 'test-token', 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $fields['hash'] = $hash;

        return http_build_query($fields);
    }
}
