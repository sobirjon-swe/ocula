<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ko'rik eslatmasi buyrug'i — PROJECT.md 7.11, BOSQICH-7.md §4.3.
 *
 * Asosiy kafolatlar: muddati yaqinlashgan retseptga xabar ketadi; uzoq
 * muddatlisiga ketmaydi.
 */
final class CheckupReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reminds_a_customer_whose_prescription_expires_soon(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $customer = Customer::factory()->create(['telegram_id' => 42]);

        // Standart sozlama: muddat tugashidan 14 kun oldin (config/optika.php).
        $prescription = Prescription::factory()->create([
            'customer_id' => $customer->id,
            'valid_until' => now()->addDays(14)->toDateString(),
        ]);

        $this->artisan('telegram:remind-checkups')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 42);

        $this->assertSame(
            1,
            TelegramNotification::query()->where('source_id', $prescription->id)->count(),
        );
    }

    #[Test]
    public function it_skips_a_prescription_that_expires_far_in_the_future(): void
    {
        Http::fake();

        $customer = Customer::factory()->create(['telegram_id' => 42]);
        Prescription::factory()->create([
            'customer_id' => $customer->id,
            'valid_until' => now()->addMonths(6)->toDateString(),
        ]);

        $this->artisan('telegram:remind-checkups')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
