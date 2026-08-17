<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Actions\Trip;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Enums\TripStatus;
use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Delivery\Models\TripStop;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marshrutga to'xtash qo'shish — PROJECT.md §6.7, BOSQICH-8.md §5 #2.
 *
 * **Yangi transfer bu yerda yaratilmaydi.** Filial to'xtashi faqat
 * allaqachon `SendTransfer` bilan `own_driver` usulida jo'natilgan
 * transferni oladi va uni shu reysga bog'laydi (`transfers.trip_id`) —
 * ikkita alohida "yetkazish yarat" yo'li bo'lmasin degan qaror.
 */
final class AddTripStop
{
    public function handle(
        User $author,
        Trip $trip,
        TripStopType $type,
        ?Transfer $transfer = null,
        ?Order $order = null,
        ?string $address = null,
        ?string $lat = null,
        ?string $lng = null,
        ?Money $cashToCollect = null,
    ): TripStop {
        if ($trip->status !== TripStatus::Planned) {
            throw ValidationException::withMessages([
                'trip' => __('delivery::trip.stops_only_while_planned'),
            ]);
        }

        return DB::transaction(fn (): TripStop => match ($type) {
            TripStopType::Branch => $this->addBranchStop($trip, $transfer),
            TripStopType::Customer => $this->addCustomerStop($trip, $order, $address, $lat, $lng, $cashToCollect),
        });
    }

    private function addBranchStop(Trip $trip, ?Transfer $transfer): TripStop
    {
        if ($transfer === null) {
            throw ValidationException::withMessages([
                'transfer_id' => __('delivery::trip.transfer_required'),
            ]);
        }

        if ($transfer->delivery_method !== DeliveryMethod::OwnDriver || $transfer->driver_id !== $trip->driver_id) {
            throw ValidationException::withMessages([
                'transfer_id' => __('delivery::trip.transfer_wrong_driver'),
            ]);
        }

        if ($transfer->status !== TransferStatus::Sent) {
            throw ValidationException::withMessages([
                'transfer_id' => __('delivery::trip.transfer_not_sent'),
            ]);
        }

        if ($transfer->trip_id !== null) {
            throw ValidationException::withMessages([
                'transfer_id' => __('delivery::trip.transfer_already_on_trip'),
            ]);
        }

        $toLocation = $transfer->toLocation()->withoutGlobalScopes()->firstOrFail();

        $stop = TripStop::create([
            'trip_id' => $trip->id,
            'sequence' => $this->nextSequence($trip),
            'type' => TripStopType::Branch,
            'branch_id' => $toLocation->branch_id,
            'transfer_id' => $transfer->id,
            'status' => TripStopStatus::Pending,
        ]);

        $transfer->update(['trip_id' => $trip->id]);

        return $stop;
    }

    private function addCustomerStop(
        Trip $trip,
        ?Order $order,
        ?string $address,
        ?string $lat,
        ?string $lng,
        ?Money $cashToCollect,
    ): TripStop {
        if ($order === null) {
            throw ValidationException::withMessages([
                'order_id' => __('delivery::trip.order_required'),
            ]);
        }

        if ($order->customer_id === null) {
            throw ValidationException::withMessages([
                'order_id' => __('delivery::trip.order_has_no_customer'),
            ]);
        }

        if ($address === null || trim($address) === '') {
            throw ValidationException::withMessages([
                'address' => __('delivery::trip.address_required'),
            ]);
        }

        $default = $order->total->minus($order->paidAmount());

        return TripStop::create([
            'trip_id' => $trip->id,
            'sequence' => $this->nextSequence($trip),
            'type' => TripStopType::Customer,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'cash_to_collect' => ($cashToCollect ?? ($default->isPositive() ? $default : Money::zero()))->toString(),
            'status' => TripStopStatus::Pending,
        ]);
    }

    private function nextSequence(Trip $trip): int
    {
        return 1 + (int) $trip->stops()->max('sequence');
    }
}
