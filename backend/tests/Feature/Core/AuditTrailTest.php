<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class AuditTrailTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function domain_changes_record_actor_before_and_after_without_credentials(): void
    {
        $this->seedPermissions();
        $director = $this->actingAsDirector();
        $user = User::factory()->create(['name' => 'Old name']);
        $user->update(['name' => 'New name', 'password' => 'a-secret-not-for-the-log']);
        $log = Activity::query()->where('subject_type', User::class)->where('subject_id', $user->id)->where('event', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertSame($director->id, $log->causer_id);
        $this->assertSame('Old name', $log->properties->get('old')['name']);
        $this->assertSame('New name', $log->properties->get('attributes')['name']);
        $this->assertArrayNotHasKey('password', $log->properties->get('attributes'));
    }
}
