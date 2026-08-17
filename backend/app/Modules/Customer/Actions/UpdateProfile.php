<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Sales\Models\Customer;
use Illuminate\Validation\ValidationException;

/**
 * Mijoz o'z profilini yangilaydi — PERMISSIONS.md §12
 * (`customer.profile.update`: "Telefon, til o'zgartirish").
 */
final class UpdateProfile
{
    public function handle(Customer $customer, ?string $phone, ?string $locale, ?string $name): Customer
    {
        if ($phone !== null) {
            $taken = Customer::query()
                ->where('phone', $phone)
                ->where('id', '!=', $customer->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'phone' => __('sales::customer.phone_taken'),
                ]);
            }
        }

        $customer->update(array_filter([
            'phone' => $phone,
            'locale' => $locale,
            'name' => $name,
        ], static fn (mixed $value): bool => $value !== null));

        return $customer;
    }
}
