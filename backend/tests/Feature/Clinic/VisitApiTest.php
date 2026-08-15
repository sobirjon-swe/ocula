<?php

declare(strict_types=1);

namespace Tests\Feature\Clinic;

use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Enums\VisitStatus;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Vizit va navbat — PROJECT.md §6.5, ENUMS.md §5.
 *
 * Asosiy kafolatlar: navbat raqami filial va kun kesimida ketma-ket
 * beriladi; bir mijoz bir kunda ikki marta navbatga tushmaydi;
 * boshlangan ko'rik bekor qilinmaydi, yakunlanadi.
 */
final class VisitApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->create(['code' => 'K']);
        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function the_queue_number_starts_at_one_and_counts_up(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/visits', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.queue_number', 1)
            ->assertJsonPath('data.status', VisitStatus::Waiting->value);

        $this->postAction('/api/v1/visits', [
            ...$this->payload(),
            'customer_id' => Customer::factory()->create()->id,
        ])->assertCreated()->assertJsonPath('data.queue_number', 2);
    }

    /**
     * Raqam **filial kesimida**: ikkinchi filialda yana 1 dan boshlanadi.
     */
    #[Test]
    public function each_branch_counts_its_own_queue(): void
    {
        $other = Branch::factory()->create();

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $this->postAction('/api/v1/visits', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.queue_number', 1);

        $this->actingAsEmployee(Role::Seller, $other);
        $this->postAction('/api/v1/visits', [
            ...$this->payload(),
            'branch_id' => $other->id,
        ])->assertCreated()->assertJsonPath('data.queue_number', 1);
    }

    /**
     * Kechagi navbat bugungisiga qo'shilmaydi.
     */
    #[Test]
    public function yesterdays_queue_does_not_continue_into_today(): void
    {
        Visit::factory()->for($this->branch)->create([
            'queue_number' => 40,
            'queue_date' => now()->subDay()->toDateString(),
            'status' => VisitStatus::Finished,
        ]);

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/visits', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.queue_number', 1);
    }

    #[Test]
    public function the_same_customer_is_not_queued_twice_in_one_day(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/visits', $this->payload())->assertCreated();

        $this->postAction('/api/v1/visits', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseCount('visits', 1);
    }

    /**
     * Ko'rikni boshlagan shifokor vizitga yoziladi — keyin "kim ko'rdi"
     * degan savolga javob qoladi (7.14).
     */
    #[Test]
    public function starting_the_exam_records_the_doctor(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->postAction('/api/v1/visits', $this->payload())->json('data.id');

        $doctor = $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction("/api/v1/visits/{$id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', VisitStatus::InProgress->value)
            ->assertJsonPath('data.doctor_id', $doctor->id);

        $this->postAction("/api/v1/visits/{$id}/finish")
            ->assertOk()
            ->assertJsonPath('data.status', VisitStatus::Finished->value);
    }

    #[Test]
    public function an_exam_under_way_can_not_be_cancelled(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->postAction('/api/v1/visits', $this->payload())->json('data.id');

        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $this->postAction("/api/v1/visits/{$id}/start")->assertOk();

        $this->postAction("/api/v1/visits/{$id}/cancel")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * "Kelmadi" bekor qilishdan ataylab ajratilgan: hisobotda
     * shifokorning bo'sh o'tirgan vaqti ko'rinishi kerak.
     */
    #[Test]
    public function a_no_show_is_recorded_apart_from_a_cancellation(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);
        $id = $this->postAction('/api/v1/visits', $this->payload())->json('data.id');

        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction("/api/v1/visits/{$id}/no-show")
            ->assertOk()
            ->assertJsonPath('data.status', VisitStatus::NoShow->value);
    }

    #[Test]
    public function the_live_queue_shows_only_open_visits_of_today(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $waiting = $this->postAction('/api/v1/visits', $this->payload())->json('data.id');

        $finished = $this->postAction('/api/v1/visits', [
            ...$this->payload(),
            'customer_id' => Customer::factory()->create()->id,
        ])->json('data.id');

        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $this->postAction("/api/v1/visits/{$finished}/start")->assertOk();
        $this->postAction("/api/v1/visits/{$finished}/finish")->assertOk();

        $response = $this->getJson('/api/v1/visits/queue')->assertOk();

        $this->assertSame([(int) $waiting], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function a_qr_visit_keeps_its_source(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/visits', [
            ...$this->payload(),
            'source' => VisitSource::Qr->value,
        ])->assertCreated()->assertJsonPath('data.source', VisitSource::Qr->value);
    }

    #[Test]
    public function a_master_may_not_open_a_visit(): void
    {
        $this->actingAsEmployee(Role::Master, $this->branch);

        $this->postAction('/api/v1/visits', $this->payload())->assertForbidden();
    }

    /**
     * Sotuvchi navbatga qo'shadi, lekin ro'yxatni ko'rish uchun
     * `clinic.visit.view_any` kerak — u shifokorda va filial
     * boshlig'ida (PERMISSIONS.md §5).
     */
    #[Test]
    public function the_queue_is_limited_to_the_own_branch(): void
    {
        Visit::factory()->for(Branch::factory()->create())->create();

        $this->actingAsEmployee(Role::Seller, $this->branch);
        $mine = $this->postAction('/api/v1/visits', $this->payload())->json('data.id');

        $this->actingAsEmployee(Role::Doctor, $this->branch);
        $response = $this->getJson('/api/v1/visits')->assertOk();

        $this->assertSame([(int) $mine], array_column($response->json('data'), 'id'));
    }

    #[Test]
    public function a_seller_may_queue_a_customer_but_not_browse_the_queue(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/visits', $this->payload())->assertCreated();
        $this->getJson('/api/v1/visits')->assertForbidden();
    }

    #[Test]
    public function a_foreign_visit_is_invisible(): void
    {
        $foreign = Visit::factory()->for(Branch::factory()->create())->create();

        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->getJson("/api/v1/visits/{$foreign->id}")->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
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
