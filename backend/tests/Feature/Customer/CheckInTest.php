<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Clinic\Actions\Visit\CreateVisit;
use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsCustomer;
use Tests\TestCase;

final class CheckInTest extends TestCase
{
    use ActsAsCustomer, RefreshDatabase;

    public function test_check_in_creates_only_own_visit_and_same_request_is_idempotent(): void
    {
        $this->seedCustomerPermissions();
        $customer = $this->actingAsCustomer();
        $branch = Branch::factory()->create();
        $key = (string) Str::uuid();
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/customer/visits', ['branch_id' => $branch->id])->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/customer/visits', ['branch_id' => $branch->id])->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $visit = Visit::withoutGlobalScopes()->sole();
        $this->assertSame($customer->id, $visit->customer_id);
        $this->assertSame('qr', $visit->source->value);
        $this->assertNull($visit->created_by);
        $this->getJson('/api/v1/customer/visits')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visit->id);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/customer/visits', ['branch_id' => $branch->id])->assertUnprocessable();
    }

    public function test_check_in_rejects_spoofed_identity_inactive_branch_and_anonymous_access(): void
    {
        $branch = Branch::factory()->create(['is_active' => false]);
        $this->getJson('/api/v1/customer/visits')->assertUnauthorized();
        $this->seedCustomerPermissions();
        $this->actingAsCustomer();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/customer/visits', ['branch_id' => $branch->id])->assertUnprocessable();
        $branch->update(['is_active' => true]);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/customer/visits', ['branch_id' => $branch->id, 'customer_id' => Customer::factory()->create()->id])->assertUnprocessable();
        $this->assertSame(0, Visit::withoutGlobalScopes()->count());
    }

    public function test_local_midnight_check_in_is_visible_in_the_doctors_today_queue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 20:00:00+00'));
        $this->seed(RolePermissionSeeder::class);
        $branch = Branch::factory()->create();
        $visit = app(CreateVisit::class)->handle(null, $branch, Customer::factory()->create()->id, VisitSource::Qr);
        $this->assertSame('2026-11-01', $visit->queue_date?->toDateString());
        $doctor = User::factory()->for($branch)->create();
        $doctor->assignRole('doctor');
        $this->actingAs($doctor, 'sanctum')->getJson('/api/v1/visits/queue')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visit->id);
    }
}
