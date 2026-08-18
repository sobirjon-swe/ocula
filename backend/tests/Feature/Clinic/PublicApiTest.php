<?php

declare(strict_types=1);

namespace Tests\Feature\Clinic;

use App\Modules\Catalog\Models\Service;
use App\Modules\Clinic\Enums\AppointmentRequestStatus;
use App\Modules\Clinic\Models\AppointmentRequest;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Landing sayt uchun ochiq endpointlar — BOSQICH-11.md.
 *
 * Asosiy kafolat: `auth:sanctum` talab qilinmaydi; faqat faol
 * filiallar/xizmatlar chiqadi; navbat so'rovi hisobsiz yoziladi va
 * real navbatga tushmaydi.
 */
final class PublicApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function it_lists_only_active_branches_without_authentication(): void
    {
        Branch::factory()->create(['name' => 'Faol filial', 'is_active' => true]);
        Branch::factory()->create(['name' => 'Yopilgan filial', 'is_active' => false]);

        $response = $this->getJson('/api/v1/public/branches')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Faol filial'], $names);
    }

    #[Test]
    public function it_lists_only_active_services_without_authentication(): void
    {
        Service::factory()->create(['name' => 'Ko\'z tekshiruvi', 'is_active' => true]);
        Service::factory()->create(['name' => 'Eskirgan xizmat', 'is_active' => false]);

        $response = $this->getJson('/api/v1/public/services')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(["Ko'z tekshiruvi"], $names);
    }

    #[Test]
    public function a_guest_submits_an_appointment_request(): void
    {
        $branch = Branch::factory()->create();

        $this->postJson('/api/v1/public/appointments', [
            'branch_id' => $branch->id,
            'name' => 'Aziz',
            'phone' => '+998901234567',
            'note' => "Ko'z tekshiruvi kerak",
        ])->assertCreated()->assertJsonPath('data.status', AppointmentRequestStatus::New->value);

        $this->assertSame(1, AppointmentRequest::query()->count());
    }

    #[Test]
    public function a_doctor_triages_the_request_and_the_response_reflects_who_handled_it(): void
    {
        $this->seedPermissions();
        $branch = Branch::factory()->create();
        $appointment = AppointmentRequest::factory()->create(['branch_id' => $branch->id]);

        $doctor = $this->actingAsEmployee(Role::Doctor, $branch);

        $this->putJson("/api/v1/appointment-requests/{$appointment->id}/status", [
            'status' => AppointmentRequestStatus::Contacted->value,
        ])->assertOk()->assertJsonPath('data.handled_by', $doctor->id);
    }

    #[Test]
    public function a_seller_can_not_triage_appointment_requests(): void
    {
        $this->seedPermissions();
        $branch = Branch::factory()->create();
        $appointment = AppointmentRequest::factory()->create(['branch_id' => $branch->id]);

        $this->actingAsEmployee(Role::Seller, $branch);

        $this->putJson("/api/v1/appointment-requests/{$appointment->id}/status", [
            'status' => AppointmentRequestStatus::Contacted->value,
        ])->assertForbidden();
    }
}
