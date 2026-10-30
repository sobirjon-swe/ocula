<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class DailySummaryTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function the_director_receives_the_daily_branch_summary_once(): void
    {
        $this->seedPermissions();
        $this->employee(Role::Director);
        Branch::factory()->create(['code' => 'A']);
        config(['services.telegram.director_chat_id' => 12345]);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $this->artisan('telegram:daily-summary', ['--date' => '2026-10-29'])->assertSuccessful();
        $this->artisan('telegram:daily-summary', ['--date' => '2026-10-29'])->assertSuccessful();
        Http::assertSentCount(1);
    }
}
