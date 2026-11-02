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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * To'xtash — PROJECT.md 7.4, BOSQICH-8.md §6.
 */
final class TripStopController extends ApiController
{
    public function deliver(DeliverStopRequest $request, TripStop $stop, DeliverStop $action): TripStopResource
    {
        $this->authorize('deliver', $stop);

        $photoPath = null;
        if ($request->filled('photo')) {
            $encoded = $request->string('photo')->toString();
            $bytes = base64_decode(substr($encoded, strpos($encoded, ',') + 1), true);
            $info = $bytes === false ? false : @getimagesizefromstring($bytes);
            if ($info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] > 5000 || $info[1] > 5000) {
                throw ValidationException::withMessages(['photo' => __('delivery::stop.invalid_photo')]);
            }
            $photoPath = 'delivery/'.$stop->id.'/'.Str::uuid().'.'.match ($info['mime']) {
                'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
            };
            Storage::disk('local')->put($photoPath, $bytes);
        }

        $result = $action->handle(
            $this->currentUser($request),
            $stop,
            lat: $request->input('lat') !== null ? (string) $request->input('lat') : null,
            lng: $request->input('lng') !== null ? (string) $request->input('lng') : null,
            photoPath: $photoPath,
        );

        return new TripStopResource($result);
    }

    public function fail(FailStopRequest $request, TripStop $stop, FailStop $action): TripStopResource
    {
        $this->authorize('fail', $stop);

        return new TripStopResource($action->handle($stop, $request->string('reason')->toString()));
    }
}
