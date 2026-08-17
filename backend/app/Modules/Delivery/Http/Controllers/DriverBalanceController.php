<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Http\Resources\DriverBalanceResource;
use App\Modules\Delivery\Models\DriverBalance;
use App\Support\Http\ApiController;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Haydovchi qo'lidagi pul — PROJECT.md §5.2, BOSQICH-8.md §6.
 */
final class DriverBalanceController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DriverBalance::class);

        return DriverBalanceResource::collection(
            DriverBalance::query()->orderByDesc('cash_amount')->get(),
        );
    }

    public function show(User $driver): DriverBalanceResource
    {
        $balance = DriverBalance::query()->find($driver->id)
            ?? new DriverBalance(['driver_id' => $driver->id]);

        $this->authorize('view', $balance);

        return new DriverBalanceResource($balance);
    }
}
