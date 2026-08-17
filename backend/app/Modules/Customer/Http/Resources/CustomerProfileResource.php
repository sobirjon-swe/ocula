<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Resources;

use App\Modules\Sales\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mijozning o'z profili — PERMISSIONS.md §12.
 *
 * Xodimlarga ko'rinadigan `CustomerResource`dan farqli, bu yerda
 * `debt_balance` har doim ko'rinadi — bu **o'zining** qarzi, alohida
 * ruxsat kerak emas (staff ko'rinishida `sales.customer.debt.view`
 * bilan cheklangan edi, chunki har xodim boshqa mijozning qarzini
 * bilishi shart emas — bu yerda esa "boshqa mijoz" degan holat yo'q).
 *
 * @mixin Customer
 */
class CustomerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'locale' => $this->locale->value,
            'telegram_id' => $this->telegram_id,
            'debt_balance' => $this->debt_balance->toString(),
            'abandoned_orders_count' => $this->abandoned_orders_count,
            'first_visit_at' => $this->first_visit_at?->toIso8601String(),
        ];
    }
}
