<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Core\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class IdempotencyIsolationTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function another_employee_cannot_replay_a_private_response(): void
    {
        $this->seedPermissions();
        Route::middleware(['api', 'auth:sanctum', 'idempotency'])->post('/api/test-replay', fn () => response()->json(['private' => auth()->id()]));
        $first = $this->actingAsDirector();
        $this->withHeader('Idempotency-Key', 'shared-key')->postJson('/api/test-replay')->assertOk()->assertJsonPath('private', $first->id);
        $this->actingAsEmployee(Role::Accountant);
        $this->postJson('/api/test-replay')->assertConflict()->assertJsonMissing(['private' => $first->id]);
    }

    #[Test]
    public function an_exception_releases_the_claim_so_the_same_operation_can_retry(): void
    {
        $this->seedPermissions();
        $this->actingAsDirector();
        $attempts = 0;
        Route::middleware(['api', 'auth:sanctum', 'idempotency'])->post('/api/test-retry', function () use (&$attempts) {
            if (++$attempts === 1) {
                throw ValidationException::withMessages(['input' => 'Try again']);
            }

            return response()->json(['ok' => true]);
        });
        $this->withHeader('Idempotency-Key', 'retry-key')->postJson('/api/test-retry')->assertUnprocessable();
        $this->postJson('/api/test-retry')->assertOk()->assertJsonPath('ok', true);
    }
}
