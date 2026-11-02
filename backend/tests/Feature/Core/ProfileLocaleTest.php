<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class ProfileLocaleTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function an_employee_changes_only_their_own_locale(): void
    {
        $this->seedPermissions();
        $actor = $this->actingAsEmployee(Role::Seller, Branch::factory()->create());
        $this->putJson('/api/v1/auth/locale', ['locale' => 'uz-cyrl', 'debt_limit' => '99999999'])->assertOk();
        $this->assertSame('uz-cyrl', $actor->fresh()->locale->value);
        $this->assertNotSame('99999999.00', $actor->fresh()->debt_limit->toString());
        $this->putJson('/api/v1/auth/locale', ['locale' => 'xx'])->assertUnprocessable();
    }

    #[Test]
    public function database_connections_store_timestamps_in_utc_even_on_a_local_timezone_server(): void
    {
        $this->assertSame('UTC', DB::selectOne("SELECT current_setting('TimeZone') AS tz")->tz);
    }
}
