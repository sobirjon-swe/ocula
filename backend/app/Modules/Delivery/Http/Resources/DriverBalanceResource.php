<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Http\Resources;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\DriverBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * Haydovchi qo'lidagi pul — SCHEMA.md, PROJECT.md §5.2.
 *
 * @mixin DriverBalance
 */
class DriverBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // A ready customer order is one packed handover; transfer goods are
        // counted as individual stock units in the driver's transit location.
        $packages = DB::table('trip_stops')
            ->join('trips', 'trips.id', '=', 'trip_stops.trip_id')
            ->where('trips.driver_id', $this->driver_id)->where('trips.status', 'in_progress')
            ->where('trip_stops.type', 'customer')->whereIn('trip_stops.status', ['pending', 'arrived'])
            ->whereNotNull('trip_stops.order_id')->distinct()->count('trip_stops.order_id');

        return [
            'driver_id' => $this->driver_id,
            'cash_amount' => $this->cash_amount->toString(),
            'goods_quantity' => (int) DB::table('stock_balances')
                ->join('locations', 'locations.id', '=', 'stock_balances.location_id')
                ->where('locations.type', 'transit')->where('locations.owner_type', User::class)
                ->where('locations.owner_id', $this->driver_id)->sum('stock_balances.quantity') + $packages,
            'customer_packages' => $packages,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
