<?php

declare(strict_types=1);

namespace Tests\Feature\Clinic;

use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_customers_sharing_the_public_site_ip_are_not_rate_limited_together(): void
    {
        $branch = Branch::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/api/v1/public/appointments', ['branch_id' => $branch->id, 'name' => 'Guest', 'phone' => '+9989012345'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)])->assertCreated();
        }
    }

    public function test_formatting_does_not_bypass_the_phone_rate_limit(): void
    {
        $branch = Branch::factory()->create();
        foreach (['+998 90 123 45 67', '+998901234567', '(998) 90-123-45-67'] as $phone) {
            $this->postJson('/api/v1/public/appointments', ['branch_id' => $branch->id, 'name' => 'Guest', 'phone' => $phone])->assertCreated();
        }
        $this->postJson('/api/v1/public/appointments', ['branch_id' => $branch->id, 'name' => 'Guest', 'phone' => '+998901234567'])->assertStatus(429)->assertHeader('Retry-After');
    }
}
