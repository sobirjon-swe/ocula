<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Actions\UpdateProfile;
use App\Modules\Customer\Http\Requests\UpdateProfileRequest;
use App\Modules\Customer\Http\Resources\CustomerProfileResource;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijoz o'z profili — PERMISSIONS.md §12.
 */
final class ProfileController extends CustomerApiController
{
    public function show(Request $request): CustomerProfileResource
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.profile.view')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return new CustomerProfileResource($customer);
    }

    public function update(UpdateProfileRequest $request, UpdateProfile $action): CustomerProfileResource
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.profile.update')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $updated = $action->handle(
            $customer,
            $request->string('phone')->toString() ?: null,
            $request->string('locale')->toString() ?: null,
            $request->string('name')->toString() ?: null,
        );

        return new CustomerProfileResource($updated);
    }
}
