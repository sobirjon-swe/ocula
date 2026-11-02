<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AbandonedOrdersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stale_ready_orders_are_marked_once_and_customer_history_is_preserved(): void
    {
        $customer = Customer::factory()->create();
        $old = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'ready', 'status_changed_at' => now()->subDays(31)]);
        $new = Order::factory()->create(['customer_id' => $customer->id, 'status' => 'ready', 'status_changed_at' => now()]);
        $this->artisan('sales:mark-abandoned')->assertSuccessful();
        $this->artisan('sales:mark-abandoned')->assertSuccessful();
        $this->assertNotNull($old->fresh()->abandoned_at);
        $this->assertNull($new->fresh()->abandoned_at);
        $this->assertSame(1, $customer->fresh()->abandoned_orders_count);
    }
}
