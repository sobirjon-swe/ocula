<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsCustomer;
use Tests\TestCase;

/**
 * Mijozning o'z retseptlari — PROJECT.md 7.11, PERMISSIONS.md §12.
 *
 * Asosiy kafolat: faqat o'z retseptlarini ko'radi, barcha filiallardan
 * (7.11'dagi "Retsept tarixi — barcha filiallar" mijozga ham tegishli).
 */
final class PrescriptionApiTest extends TestCase
{
    use ActsAsCustomer, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCustomerPermissions();
    }

    #[Test]
    public function a_customer_sees_their_prescriptions_across_all_branches(): void
    {
        $customer = $this->actingAsCustomer();

        $mineA = Prescription::factory()->create(['customer_id' => $customer->id]);
        $mineB = Prescription::factory()->create(['customer_id' => $customer->id]);
        $theirs = Prescription::factory()->create(['customer_id' => Customer::factory()->create()->id]);

        $response = $this->getJson('/api/v1/customer/prescriptions')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mineA->id));
        $this->assertTrue($ids->contains($mineB->id));
        $this->assertFalse($ids->contains($theirs->id));
    }
}
