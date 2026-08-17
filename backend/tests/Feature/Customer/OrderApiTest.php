<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Core\Models\Branch;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsCustomer;
use Tests\TestCase;

/**
 * Mijozning o'z buyurtmalari — PERMISSIONS.md §12
 * (`customer.order.view_own`).
 *
 * Asosiy kafolat: faqat o'z buyurtmalarini ko'radi, begonasi 404.
 */
final class OrderApiTest extends TestCase
{
    use ActsAsCustomer, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCustomerPermissions();
    }

    #[Test]
    public function a_customer_sees_only_their_own_orders(): void
    {
        $branch = Branch::factory()->main()->create(['code' => 'A']);
        $customer = $this->actingAsCustomer();
        $mine = Order::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id]);
        $theirs = Order::factory()->create(['branch_id' => $branch->id, 'customer_id' => Customer::factory()->create()->id]);

        $response = $this->getJson('/api/v1/customer/orders')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    #[Test]
    public function a_customer_cannot_view_someone_elses_order(): void
    {
        $branch = Branch::factory()->main()->create(['code' => 'A']);
        $this->actingAsCustomer();
        $theirs = Order::factory()->create(['branch_id' => $branch->id, 'customer_id' => Customer::factory()->create()->id]);

        $this->getJson("/api/v1/customer/orders/{$theirs->id}")->assertNotFound();
    }
}
