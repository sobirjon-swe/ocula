<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\Transfer;
use App\Support\Http\ApiController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TransferReceiptController extends ApiController
{
    public function __invoke(Transfer $transfer): StreamedResponse
    {
        $this->authorize('view', $transfer);
        $path = $transfer->taxi_receipt_path;
        abort_unless(is_string($path) && str_starts_with($path, 'transfers/'.$transfer->id.'/') && Storage::exists($path), 404);

        return Storage::response($path, headers: ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
