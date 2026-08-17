<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Delivery\Actions\TripStop\ConfirmStopDelivery;
use App\Modules\Delivery\Actions\TripStop\DisputeStopDelivery;
use App\Modules\Delivery\Models\TripStop;
use App\Support\Http\ApiResponse;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Qabul qildim" / "Yo'q" — PROJECT.md 7.4, PERMISSIONS.md §12
 * (`customer.delivery.confirm`), BOSQICH-9.md §1.
 *
 * Bir xil amal Telegram inline tugmasi orqali ham keladi
 * (`Telegram\Http\Controllers\TelegramWebhookController`, Bosqich 8) —
 * bu yerda esa Mini App'ning o'zidan, ikkalasi ham bir xil
 * `ConfirmStopDelivery`/`DisputeStopDelivery` amaliga tayanadi.
 */
final class DeliveryConfirmationController extends CustomerApiController
{
    public function confirm(Request $request, int $stop, ConfirmStopDelivery $action): JsonResponse
    {
        return $this->respond($request, $stop, fn (TripStop $model) => $action->handle($model));
    }

    public function dispute(Request $request, int $stop, DisputeStopDelivery $action): JsonResponse
    {
        return $this->respond($request, $stop, fn (TripStop $model) => $action->handle($model));
    }

    /**
     * @param  callable(TripStop): TripStop  $action
     */
    private function respond(Request $request, int $stopId, callable $action): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.delivery.confirm')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $stop = TripStop::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($stopId);

        $result = $action($stop);

        return ApiResponse::data([
            'confirmation_status' => $result->confirmation_status?->value,
        ]);
    }
}
