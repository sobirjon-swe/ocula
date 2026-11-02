<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Models\TripStop;
use App\Support\Http\ApiController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DeliveryPhotoController extends ApiController
{
    public function __invoke(TripStop $stop): StreamedResponse
    {
        $this->authorize('view', $stop->trip);
        $path = $stop->photo_path;
        abort_unless(is_string($path) && str_starts_with($path, 'delivery/'.$stop->id.'/') && Storage::exists($path), 404);

        return Storage::response($path, headers: ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
