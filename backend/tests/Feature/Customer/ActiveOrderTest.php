<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsCustomer;
use Tests\TestCase;

final class ActiveOrderTest extends TestCase
{
    use ActsAsCustomer, RefreshDatabase;

    public function test_active_filter_finds_old_unfinished_order_without_exposing_other_customers(): void
    {
        $this->seedCustomerPermissions();
        $customer = $this->actingAsCustomer();
        $active = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'in_workshop', 'created_at' => now()->subMonth()]);
        Order::factory()->count(25)->delivered()->create(['customer_id' => $customer->id]);
        Order::factory()->create(['status' => 'new']);

        $this->getJson('/api/v1/customer/orders?active=1&per_page=20')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/customer/orders?per_page=20')
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 26);
    }
}
