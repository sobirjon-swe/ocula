<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Transfer;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\Transfer;
use App\Modules\Warehouse\Models\TransferItem;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transferni qabul qilish — PROJECT.md 7.4, ENUMS.md §3.
 *
 * Qabul qiluvchi xodim **haqiqiy miqdorni** kiritadi, jo'natilganini
 * ko'r-ko'rona tasdiqlamaydi. Bu ataylab: kam kelgan tovar shu yerda
 * ushlanmasa, u keyin inventarizatsiyada "sababi noma'lum kamomad"
 * bo'lib chiqardi va kim javobgarligi ma'lum bo'lmasdi.
 *
 * Uchta natija bo'lishi mumkin:
 * - hamma narsa keldi → `received`;
 * - kam keldi → yetmagan qism transitdan **hisobdan chiqariladi**
 *   (`write_off`), hujjat `partially_received` bo'ladi va
 *   `has_discrepancy` bayrog'i direktorga signal beradi (7.4);
 *   ortiqcha kiritishga yo'l qo'yilmaydi.
 *
 * Yakunda transit bo'shaydi: nima kelgani qabul qiluvchida, nima
 * yetmagani hisobdan chiqarilgan.
 */
final class ReceiveTransfer
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  array<int, array{item_id: int, quantity: int}>  $received
     */
    public function handle(User $receiver, Transfer $transfer, array $received): Transfer
    {
        if (! $transfer->status->canBeReceived()) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::transfer.not_on_the_road'),
            ]);
        }

        $quantities = $this->mapQuantities($transfer, $received);

        return DB::transaction(function () use ($receiver, $transfer, $quantities): Transfer {
            // Global scope chetlab o'tiladi: transit **jo'natuvchi**
            // filialga tegishli va qabul qiluvchi xodimga ko'rinmaydi.
            // Cheklov hujjat ro'yxatlari uchun, hujjatning ichki
            // mexanikasi uchun emas.
            $transit = $transfer->transitLocation()->withoutGlobalScopes()->firstOrFail();
            $to = $transfer->toLocation()->withoutGlobalScopes()->firstOrFail();

            $hasDiscrepancy = false;

            foreach ($transfer->items()->get() as $item) {
                $quantity = $quantities[$item->id];

                $this->acceptItem($receiver, $transfer, $item, $quantity, $transit, $to);

                $hasDiscrepancy = $hasDiscrepancy || $quantity < $item->qty_sent;
            }

            $transfer->update([
                'status' => $hasDiscrepancy
                    ? TransferStatus::PartiallyReceived
                    : TransferStatus::Received,
                'has_discrepancy' => $hasDiscrepancy,
                'received_by' => $receiver->id,
                'received_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Bitta satrni transitdan qabul qiluvchi omborga o'tkazadi,
     * yetmagan qismini hisobdan chiqaradi.
     */
    private function acceptItem(
        User $receiver,
        Transfer $transfer,
        TransferItem $item,
        int $quantity,
        Location $transit,
        Location $to,
    ): void {
        $unitCost = $item->unit_cost;

        if ($quantity > 0) {
            $this->ledger->issue(
                $receiver, $transit, $item->variant_id, $quantity,
                MovementType::TransferOut, $transfer,
            );

            $this->ledger->receive(
                $receiver, $to, $item->variant_id, $quantity,
                $unitCost ?? Money::zero(),
                MovementType::TransferIn, $transfer,
            );
        }

        $missing = $item->qty_sent - $quantity;

        if ($missing > 0) {
            // Yetmagan qism transitda osilib qolmaydi — u hisobdan
            // chiqariladi va sababi yozuvda qoladi (ENUMS.md §3).
            $this->ledger->issue(
                $receiver, $transit, $item->variant_id, $missing,
                MovementType::WriteOff, $transfer,
                __('warehouse::transfer.write_off_reason', ['number' => $transfer->number]),
            );
        }

        $item->update(['qty_received' => $quantity]);
    }

    /**
     * So'rovdagi miqdorlarni satrlarga moslaydi va tekshiradi.
     *
     * Har bir satr uchun miqdor **majburiy**: tushirib qoldirilgan satr
     * "nol keldi" degan ma'noni bermasligi kerak, aks holda bir satr
     * unutilib, tovar jimgina hisobdan chiqib ketardi.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $received
     * @return array<int, int>
     */
    private function mapQuantities(Transfer $transfer, array $received): array
    {
        $byId = [];

        foreach ($received as $row) {
            $byId[$row['item_id']] = $row['quantity'];
        }

        $quantities = [];

        foreach ($transfer->items()->get() as $item) {
            if (! array_key_exists($item->id, $byId)) {
                throw ValidationException::withMessages([
                    'items' => __('warehouse::transfer.item_missing_in_count'),
                ]);
            }

            $quantity = $byId[$item->id];

            if ($quantity < 0 || $quantity > $item->qty_sent) {
                throw ValidationException::withMessages([
                    'items' => __('warehouse::transfer.received_more_than_sent', [
                        'sent' => $item->qty_sent,
                    ]),
                ]);
            }

            $quantities[$item->id] = $quantity;
        }

        return $quantities;
    }
}
