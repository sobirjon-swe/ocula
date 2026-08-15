<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Transfer;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Documents\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfer qoralamasini yaratish — SCHEMA.md §3.
 *
 * Ombor bu yerda **tegilmaydi**: hujjat qog'ozda tug'iladi, tovar esa
 * jo'natilganda chiqadi (`SendTransfer`). Shu sababli yetkazish usuli
 * ham hozir so'ralmaydi — u jo'natish paytida ma'lum bo'ladi.
 *
 * Raqam jo'natuvchi filial kodidan hosil bo'ladi (ANALIZ 3.12).
 */
final class CreateTransfer
{
    /**
     * @param  array<int, array{variant_id: int, quantity: int}>  $items
     */
    public function handle(
        User $author,
        Location $from,
        Location $to,
        array $items,
        ?string $note = null,
    ): Transfer {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('warehouse::transfer.no_items'),
            ]);
        }

        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_location_id' => __('warehouse::transfer.same_location'),
            ]);
        }

        if ($from->branch_id === $to->branch_id) {
            throw ValidationException::withMessages([
                'to_location_id' => __('warehouse::transfer.same_branch'),
            ]);
        }

        return DB::transaction(function () use ($author, $from, $to, $items, $note): Transfer {
            $branch = $from->branch()->firstOrFail();

            $transfer = Transfer::create([
                'number' => DocumentNumber::next('transfer', $branch->id, $branch->code),
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'status' => TransferStatus::Draft,
                // Usul jo'natishda tanlanadi; ustun `NOT NULL` bo'lgani
                // uchun qoralamada ham qiymat turishi kerak.
                'delivery_method' => DeliveryMethod::OwnDriver,
                'note' => $note,
                'created_by' => $author->id,
            ]);

            foreach ($items as $row) {
                $transfer->items()->create([
                    'variant_id' => $row['variant_id'],
                    'qty_sent' => $row['quantity'],
                ]);
            }

            return $transfer;
        });
    }
}
