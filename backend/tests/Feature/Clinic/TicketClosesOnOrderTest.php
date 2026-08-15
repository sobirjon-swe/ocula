<?php

declare(strict_types=1);

namespace Tests\Feature\Clinic;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\Concerns\BuildsSalesFixtures;
use Tests\TestCase;

/**
 * Tiketning yopilishi — PROJECT.md 7.11.
 *
 * Tiketning butun vazifasi sotuvchiga "bu retsept bo'yicha ish bor"
 * deb turish. Buyurtma ochilgach vazifa bajarildi va tiket yopiladi.
 *
 * Lekin faqat **o'z filialining** tiketi: mijoz boshqa filialga kelib
 * eski retsepti bo'yicha buyurtma bersa (7.11 buni ataylab ruxsat
 * beradi), asl filialdagi ish hali bajarilmagan va uning tiketi ochiq
 * qoladi.
 */
final class TicketClosesOnOrderTest extends TestCase
{
    use ActsAsEmployee, BuildsSalesFixtures, RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->setUpSalesFixtures();
        $this->stockUp(10, '75000');

        $this->customer = Customer::factory()->create();
    }

    #[Test]
    public function ordering_by_the_prescription_closes_the_ticket(): void
    {
        $prescription = $this->prescriptionIn($this->branch);

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'customer_id' => $this->customer->id,
            'prescription_id' => $prescription->id,
        ])->assertCreated();

        $this->assertFalse($prescription->fresh()?->ticket_active);

        // Buyurtma retseptga bog'landi — "nima uchun yasaldi" ko'rinib
        // tursin.
        $this->assertSame($prescription->id, Order::firstOrFail()->prescription_id);
    }

    /**
     * Boshqa filialning tiketi **ochiq qoladi**: u yerdagi ish hali
     * bajarilmagan va uni bu buyurtma yopmaydi.
     */
    #[Test]
    public function ordering_elsewhere_leaves_the_original_ticket_open(): void
    {
        $other = Branch::factory()->create(['code' => 'C']);
        $prescription = $this->prescriptionIn($other);

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', [
            ...$this->quickSalePayload(),
            'customer_id' => $this->customer->id,
            'prescription_id' => $prescription->id,
        ])->assertCreated();

        $this->assertTrue($prescription->fresh()?->ticket_active);
    }

    #[Test]
    public function an_order_without_a_prescription_touches_nothing(): void
    {
        $prescription = $this->prescriptionIn($this->branch);

        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/orders', $this->quickSalePayload())->assertCreated();

        $this->assertTrue($prescription->fresh()?->ticket_active);
    }

    private function prescriptionIn(Branch $branch): Prescription
    {
        return Prescription::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $branch->id,
            'ticket_branch_id' => $branch->id,
            'doctor_id' => $this->author->id,
        ]);
    }
}
