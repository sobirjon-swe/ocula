<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Actions\TripStop\DeliverStop;
use App\Modules\Delivery\Actions\TripStop\FailStop;
use App\Modules\Delivery\Http\Requests\DeliverStopRequest;
use App\Modules\Delivery\Http\Requests\FailStopRequest;
use App\Modules\Delivery\Http\Resources\TripStopResource;
use App\Modules\Delivery\Models\TripStop;
use App\Support\Http\ApiController;

/**
 * To'xtash — PROJECT.md 7.4, BOSQICH-8.md §6.
 */
final class TripStopController extends ApiController
{
    public function deliver(DeliverStopRequest $request, TripStop $stop, DeliverStop $action): TripStopResource
    {
        $this->authorize('deliver', $stop);

        $result = $action->handle(
            $this->currentUser($request),
            $stop,
            lat: $request->input('lat') !== null ? (string) $request->input('lat') : null,
            lng: $request->input('lng') !== null ? (string) $request->input('lng') : null,
            photoPath: $request->string('photo_path')->toString() ?: null,
        );

        return new TripStopResource($result);
    }

    public function fail(FailStopRequest $request, TripStop $stop, FailStop $action): TripStopResource
    {
        $this->authorize('fail', $stop);

        return new TripStopResource($action->handle($stop, $request->string('reason')->toString()));
    }
}
