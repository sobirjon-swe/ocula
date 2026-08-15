<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Transfer;

use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\Transfer;
use App\Modules\Warehouse\Models\TransferItem;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transferni jo'natish — PROJECT.md 7.10, ANALIZ 3.2, 3.10.
 *
 * Tovar jo'natuvchi filialdan chiqadi va **yo'qolmaydi**: u
 * `transit` location'ga kiradi va qabul qilinmaguncha o'sha yerda
 * turadi. Ikkita harakat bitta tranzaksiyada — biri o'tib ikkinchisi
 * o'tmasa, tovar hech qayerda bo'lmay qolardi.
 *
 * Tannarx **jo'natish paytida** FIFO dan hisoblanadi va transitga ham,
 * qabul qiluvchi filialga ham o'sha qiymat bilan kiradi. Aks holda
 * transferning o'zi foyda yoki zarar yasab qo'yardi.
 *
 * "Yo'lda" turgan qoldiq egasi usulga qarab boshqacha (3.2): haydovchi
 * va xodimda javobgar odam bor, taksida esa yo'q — o'shanda egasi
 * transferning o'zi bo'ladi.
 */
final class SendTransfer
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(
        User $sender,
        Transfer $transfer,
        DeliveryMethod $method,
        ?int $carrierId = null,
        ?Money $taxiCost = null,
        ?string $receiptPath = null,
    ): Transfer {
        if ($transfer->status !== TransferStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::transfer.not_draft'),
            ]);
        }

        $this->assertMethodIsComplete($method, $carrierId, $taxiCost);

        return DB::transaction(function () use (
            $sender, $transfer, $method, $carrierId, $taxiCost, $receiptPath
        ): Transfer {
            $from = $transfer->fromLocation()->withoutGlobalScopes()->firstOrFail();

            $transfer->update([
                'delivery_method' => $method,
                'driver_id' => $method === DeliveryMethod::OwnDriver ? $carrierId : null,
                'carrier_user_id' => $method === DeliveryMethod::ByHand ? $carrierId : null,
                'taxi_cost' => $taxiCost?->toString(),
                'taxi_receipt_path' => $receiptPath,
            ]);

            $transit = $this->transitLocation($transfer, $from, $method, $carrierId);

            foreach ($transfer->items()->get() as $item) {
                $this->moveToTransit($sender, $transfer, $item, $from, $transit);
            }

            $transfer->update([
                'transit_location_id' => $transit->id,
                'status' => TransferStatus::Sent,
                'sent_by' => $sender->id,
                'sent_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Bitta satrni ombordan chiqarib transitga kiritadi.
     */
    private function moveToTransit(
        User $sender,
        Transfer $transfer,
        TransferItem $item,
        Location $from,
        Location $transit,
    ): void {
        $out = $this->ledger->issue(
            $sender,
            $from,
            $item->variant_id,
            $item->qty_sent,
            MovementType::TransferOut,
            $transfer,
        );

        $unitCost = $out->cost_total->dividedBy($item->qty_sent);

        $this->ledger->receive(
            $sender,
            $transit,
            $item->variant_id,
            $item->qty_sent,
            $unitCost,
            MovementType::TransferIn,
            $transfer,
        );

        $item->update(['unit_cost' => $unitCost->toString()]);
    }

    /**
     * "Yo'lda" turadigan joy (3.2).
     *
     * Haydovchi va xodim uchun joy **qayta ishlatiladi**: bitta
     * haydovchining qo'lidagi hamma narsa bitta joyda turgani
     * mantiqan to'g'ri va "kimda nima bor" savoliga darrov javob
     * beradi. Taksida esa javobgar odam yo'q, shuning uchun har
     * transferga alohida joy ochiladi.
     *
     * Filial sifatida **jo'natuvchi** yoziladi (ANALIZ 3.10) — yo'ldagi
     * tovar hali jo'natuvchining javobgarligida.
     */
    private function transitLocation(
        Transfer $transfer,
        Location $from,
        DeliveryMethod $method,
        ?int $carrierId,
    ): Location {
        if ($method->transitIsOwnedByTransfer()) {
            return Location::create([
                'branch_id' => $from->branch_id,
                'type' => LocationType::Transit,
                'name' => __('warehouse::transfer.transit_taxi', ['number' => $transfer->number]),
                'owner_type' => Transfer::class,
                'owner_id' => $transfer->id,
                'is_active' => true,
            ]);
        }

        $carrier = User::query()->findOrFail($carrierId);

        $existing = Location::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $from->branch_id)
            ->where('type', LocationType::Transit)
            ->where('owner_type', User::class)
            ->where('owner_id', $carrier->id)
            ->first();

        return $existing ?? Location::create([
            'branch_id' => $from->branch_id,
            'type' => LocationType::Transit,
            'name' => __('warehouse::transfer.transit_person', ['name' => $carrier->name]),
            'owner_type' => User::class,
            'owner_id' => $carrier->id,
            'is_active' => true,
        ]);
    }

    /**
     * Usul bo'yicha majburiy maydonlar (7.10).
     *
     * Taksi xarajatisiz o'tib ketsa, "qaysi yo'nalishga qancha ketdi"
     * degan savol yana javobsiz qolardi — bu esa mexanizmning butun
     * ma'nosi edi.
     */
    private function assertMethodIsComplete(
        DeliveryMethod $method,
        ?int $carrierId,
        ?Money $taxiCost,
    ): void {
        if ($method->requiresCarrier() && $carrierId === null) {
            throw ValidationException::withMessages([
                'carrier_id' => __('warehouse::transfer.carrier_required'),
            ]);
        }

        if ($method->requiresCost() && ($taxiCost === null || ! $taxiCost->isPositive())) {
            throw ValidationException::withMessages([
                'taxi_cost' => __('warehouse::transfer.taxi_cost_required'),
            ]);
        }
    }
}
