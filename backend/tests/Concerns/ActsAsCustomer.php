<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Sales\Models\Customer;
use Database\Seeders\CustomerPermissionSeeder;

/**
 * `customer` guard uchun mijoz yasab, uning nomidan so'rov yuborish —
 * BOSQICH-9.md.
 *
 * `ActsAsEmployee`ning oynasi: xuddi shunday, `CustomerPermissionSeeder`
 * siz ruxsatlar bo'lmaydi.
 */
trait ActsAsCustomer
{
    protected function seedCustomerPermissions(): void
    {
        $this->seed(CustomerPermissionSeeder::class);
    }

    protected function actingAsCustomer(?Customer $customer = null): Customer
    {
        $customer ??= Customer::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer, 'customer');

        return $customer;
    }
}
