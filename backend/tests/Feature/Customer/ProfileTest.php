<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Sales\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsCustomer;
use Tests\TestCase;

/**
 * Mijoz o'z profili — PERMISSIONS.md §12.
 */
final class ProfileTest extends TestCase
{
    use ActsAsCustomer, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCustomerPermissions();
    }

    #[Test]
    public function a_customer_sees_their_own_profile(): void
    {
        $customer = $this->actingAsCustomer(Customer::factory()->create(['name' => 'Dilnoza']));

        $this->getJson('/api/v1/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Dilnoza');
    }

    #[Test]
    public function a_customer_can_set_their_phone_and_locale(): void
    {
        $this->actingAsCustomer(Customer::factory()->create(['phone' => null]));

        $this->putJson('/api/v1/customer/profile', [
            'phone' => '+998901234567',
            'locale' => 'ru',
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '+998901234567')
            ->assertJsonPath('data.locale', 'ru');
    }

    #[Test]
    public function a_duplicate_phone_is_rejected(): void
    {
        Customer::factory()->create(['phone' => '+998900000001']);
        $this->actingAsCustomer(Customer::factory()->create(['phone' => null]));

        $this->putJson('/api/v1/customer/profile', ['phone' => '+998900000001'])
            ->assertStatus(422);
    }

    #[Test]
    public function an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/customer/profile')->assertUnauthorized();
    }
}
