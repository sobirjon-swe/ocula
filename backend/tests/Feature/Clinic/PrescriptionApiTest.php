<?php

declare(strict_types=1);

namespace Tests\Feature\Clinic;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Setting;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Retsept va tiket — PROJECT.md 7.11, §10 (shifokor ekrani).
 *
 * Bosqichning eng muhim kafolati: **faol tiket faqat vizit bo'lgan
 * filialda ko'rinadi**, retsept tarixi esa barcha filiallarda o'qiladi.
 * A filialdagi shifokorning retsepti B filialga tushsa, B da keraksiz
 * ko'zoynak yasalib qoladi — sof brak va zarar.
 */
final class PrescriptionApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->create(['code' => 'A']);
        $this->otherBranch = Branch::factory()->create(['code' => 'B']);
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function saving_a_prescription_opens_a_ticket_in_the_visit_branch(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/prescriptions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.ticket_active', true)
            ->assertJsonPath('data.ticket_branch_id', $this->branch->id)
            ->assertJsonPath('data.od.sph', '-2.25')
            ->assertJsonPath('data.od.axis', 90);
    }

    /**
     * Amal qilish muddati sozlamadan olinadi (7.11, default 12 oy).
     */
    #[Test]
    public function the_validity_comes_from_the_setting(): void
    {
        Setting::put(SettingKey::PrescriptionValidityMonths, 6);

        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/prescriptions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.valid_until', now()->addMonths(6)->toDateString());
    }

    /**
     * **7.11 ning yuragi:** boshqa filial sotuvchisi faol tiketni
     * ko'rmaydi, lekin tarixni o'qiy oladi.
     */
    #[Test]
    public function the_active_ticket_is_confined_to_its_branch(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $this->postAction('/api/v1/prescriptions', $this->payload())->assertCreated();

        // O'z filialida tiket ro'yxatda turibdi.
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->getJson('/api/v1/prescriptions')->assertOk()->assertJsonCount(1, 'data');

        // Boshqa filialda esa yo'q — u yerda ish boshlanmasligi kerak.
        $this->actingAsEmployee(Role::Seller, $this->otherBranch);
        $this->getJson('/api/v1/prescriptions')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function the_history_is_readable_from_every_branch(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $this->postAction('/api/v1/prescriptions', $this->payload())->assertCreated();

        $this->actingAsEmployee(Role::Seller, $this->otherBranch);

        $this->getJson("/api/v1/customers/{$this->customer->id}/prescriptions")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Tiketni ko'chirish qoidadan chekinish — sabab majburiy va
     * yozib qo'yiladi (7.11).
     */
    #[Test]
    public function moving_the_ticket_requires_a_reason_and_is_logged(): void
    {
        $id = $this->write();

        $this->actingAsDirector();

        $this->postAction("/api/v1/prescriptions/{$id}/transfer-ticket", [
            'to_branch_id' => $this->otherBranch->id,
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postAction("/api/v1/prescriptions/{$id}/transfer-ticket", [
            'to_branch_id' => $this->otherBranch->id,
            'reason' => 'Mijoz B filialiga borishini aytdi',
        ])->assertOk()->assertJsonPath('data.ticket_branch_id', $this->otherBranch->id);

        $this->assertDatabaseHas('prescription_transfers', [
            'prescription_id' => $id,
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $this->otherBranch->id,
        ]);

        // Endi tiket B filialida ko'rinadi, A da yo'q.
        $this->actingAsEmployee(Role::Seller, $this->otherBranch);
        $this->getJson('/api/v1/prescriptions')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->getJson('/api/v1/prescriptions')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_seller_may_not_move_a_ticket(): void
    {
        $id = $this->write();

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction("/api/v1/prescriptions/{$id}/transfer-ticket", [
            'to_branch_id' => $this->otherBranch->id,
            'reason' => 'Shunchaki',
        ])->assertForbidden();
    }

    /**
     * Diopter oldingi retseptdan keskin farq qilsa — ogohlantirish
     * beriladi, lekin retsept **saqlanadi** (§10).
     */
    #[Test]
    public function a_large_diopter_jump_warns_but_does_not_block(): void
    {
        $doctor = $this->actingAsEmployee(Role::Doctor, $this->branch);

        Prescription::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'ticket_branch_id' => $this->branch->id,
            'doctor_id' => $doctor->id,
            'od_sph' => '-1.00',
        ]);

        $response = $this->postAction('/api/v1/prescriptions', [
            ...$this->payload(),
            'od_sph' => '-4.00',
        ])->assertCreated();

        $this->assertNotEmpty($response->json('meta.warnings'));
    }

    #[Test]
    public function a_small_change_produces_no_warning(): void
    {
        $doctor = $this->actingAsEmployee(Role::Doctor, $this->branch);

        Prescription::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'ticket_branch_id' => $this->branch->id,
            'doctor_id' => $doctor->id,
            'od_sph' => '-2.00',
        ]);

        $this->postAction('/api/v1/prescriptions', $this->payload())
            ->assertCreated()
            ->assertJsonMissingPath('meta.warnings');
    }

    /**
     * SPH/CYL 0.25 qadamli — bu "−2.5 o'rniga −25" xatosini oldini
     * oladi (§10), ya'ni haqiqiy pul yo'qotishni.
     */
    #[Test]
    public function a_dioptre_off_the_quarter_step_is_refused(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/prescriptions', [
            ...$this->payload(),
            'od_sph' => '-2.30',
        ])->assertStatus(422)->assertJsonValidationErrors('od_sph');
    }

    #[Test]
    public function an_axis_outside_the_half_circle_is_refused(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/prescriptions', [
            ...$this->payload(),
            'od_axis' => 200,
        ])->assertStatus(422)->assertJsonValidationErrors('od_axis');
    }

    #[Test]
    public function the_doctor_edits_only_their_own_prescription(): void
    {
        $id = $this->write();

        // Boshqa shifokor — o'zganikini tahrirlay olmaydi.
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->putJson("/api/v1/prescriptions/{$id}", ['od_sph' => '-3.00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('prescription');
    }

    #[Test]
    public function editing_is_closed_after_the_window(): void
    {
        $doctor = $this->actingAsEmployee(Role::Doctor, $this->branch);

        $old = Prescription::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'ticket_branch_id' => $this->branch->id,
            'doctor_id' => $doctor->id,
            'created_at' => now()->subHours(Prescription::EDIT_WINDOW_HOURS + 1),
        ]);

        $this->putJson("/api/v1/prescriptions/{$old->id}", ['od_sph' => '-3.00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('prescription');
    }

    #[Test]
    public function the_doctor_may_edit_within_the_window(): void
    {
        $doctor = $this->actingAsEmployee(Role::Doctor, $this->branch);

        $fresh = Prescription::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'ticket_branch_id' => $this->branch->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->putJson("/api/v1/prescriptions/{$fresh->id}", ['od_sph' => '-3.00'])
            ->assertOk()
            ->assertJsonPath('data.od.sph', '-3.00');
    }

    #[Test]
    public function a_seller_may_not_write_a_prescription(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/prescriptions', $this->payload())->assertForbidden();
    }

    /**
     * Retsept yozish tibbiy javobgarlik — direktorda ham bu ruxsat
     * ataylab yo'q (PERMISSIONS.md nozikliklar).
     */
    #[Test]
    public function even_the_director_may_not_write_a_prescription(): void
    {
        $this->actingAsDirector();

        $this->postAction('/api/v1/prescriptions', $this->payload())->assertForbidden();
    }

    #[Test]
    public function a_prescription_can_be_tied_to_a_visit(): void
    {
        $visit = Visit::factory()->for($this->branch)->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/prescriptions', [
            ...$this->payload(),
            'visit_id' => $visit->id,
        ])->assertCreated()->assertJsonPath('data.visit_id', $visit->id);
    }

    #[Test]
    public function a_visit_of_another_customer_is_refused(): void
    {
        $visit = Visit::factory()->for($this->branch)->create();

        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/prescriptions', [
            ...$this->payload(),
            'visit_id' => $visit->id,
        ])->assertStatus(422)->assertJsonValidationErrors('visit_id');
    }

    private function write(): int
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        return (int) $this->postAction('/api/v1/prescriptions', $this->payload())
            ->assertCreated()
            ->json('data.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'od_sph' => '-2.25',
            'od_cyl' => '-0.75',
            'od_axis' => 90,
            'os_sph' => '-2.00',
            'pd' => '62.0',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postAction(string $uri, array $payload = []): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($uri, $payload);
    }
}
