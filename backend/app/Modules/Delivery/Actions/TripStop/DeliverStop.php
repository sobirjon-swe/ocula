<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\TripStop;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Events\StopDelivered;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Delivery\Services\DriverCashLedger;
use App\Modules\Sales\Actions\Order\DeliverOrder;
use App\Modules\Sales\Actions\Payment\RecordPayment;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Models\Order;
use App\Support\Geo\Distance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Haydovchining "Yetkazdim" tasdig'i — PROJECT.md 7.4, BOSQICH-8.md §4.
 *
 * **Asimmetrik**: bu — haydovchi tomoni. Filial turidagi to'xtashda
 * transferning haqiqiy miqdorini filial xodimi alohida, mavjud
 * `ReceiveTransfer` orqali kiritadi (bu amal unga tegmaydi). Mijoz
 * turidagi to'xtashda esa shu qadamning o'zi buyurtmani topshiradi —
 * tovar aynan shu paytda mijozga o'tadi.
 */
final class DeliverStop
{
    public function __construct(
        private readonly DeliverOrder $deliverOrder,
        private readonly RecordPayment $recordPayment,
        private readonly DriverCashLedger $cashLedger,
    ) {}

    public function handle(
        User $driver,
        TripStop $stop,
        ?string $lat = null,
        ?string $lng = null,
        ?string $photoPath = null,
    ): TripStop {
        if (! $stop->status->canBeDelivered()) {
            throw ValidationException::withMessages([
                'status' => __('delivery::stop.cannot_deliver', ['status' => $stop->status->value]),
            ]);
        }

        return DB::transaction(function () use ($driver, $stop, $lat, $lng, $photoPath): TripStop {
            $stop->update([
                'status' => TripStopStatus::Delivered,
                'delivered_at' => now(),
                'delivered_lat' => $lat,
                'delivered_lng' => $lng,
                'distance_m' => $this->distanceFor($stop, $lat, $lng),
                'photo_path' => $photoPath,
                'confirmation_status' => $stop->type === TripStopType::Customer
                    ? ConfirmationStatus::Awaiting
                    : null,
            ]);

            if ($stop->type === TripStopType::Customer) {
                $this->deliverCustomerOrder($driver, $stop);
                event(new StopDelivered($stop->fresh()));
            }

            return $stop->fresh();
        });
    }

    private function deliverCustomerOrder(User $driver, TripStop $stop): void
    {
        /** @var Order $order */
        $order = $stop->order()->firstOrFail();

        if ($order->status->canBeDelivered()) {
            $this->deliverOrder->handle($driver, $order);
        }

        $remaining = $order->fresh()->total->minus($order->fresh()->paidAmount());
        $toCollect = $stop->cash_to_collect->greaterThan($remaining) ? $remaining : $stop->cash_to_collect;

        if (! $toCollect->isPositive()) {
            return;
        }

        $this->recordPayment->handle(
            $driver,
            $order->fresh(),
            $toCollect,
            PaymentMethod::Cash,
            collectedInField: true,
        );

        $this->cashLedger->credit($driver->id, $toCollect);
        $stop->trip()->increment('cash_collected', $toCollect->toString());
    }

    private function distanceFor(TripStop $stop, ?string $lat, ?string $lng): ?int
    {
        if ($lat === null || $lng === null || $stop->lat === null || $stop->lng === null) {
            return null;
        }

        return Distance::metersBetween(
            (float) $stop->lat,
            (float) $stop->lng,
            (float) $lat,
            (float) $lng,
        );
    }
}
