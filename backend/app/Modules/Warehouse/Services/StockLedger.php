<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Services;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\LayerSource;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\StockLayer;
use App\Modules\Warehouse\Models\StockLayerConsumption;
use App\Modules\Warehouse\Models\StockMovement;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ombor daftari va FIFO tannarxi — PROJECT.md 7.1, 7.20, 7.21.
 *
 * Bu klass **omborga tegadigan yagona joy**. Kontroller ham, boshqa
 * modul ham `stock_movements` ga to'g'ridan-to'g'ri yozmaydi: aks holda
 * qatlamlar bilan daftar ajralib ketadi.
 *
 * Uchta amal:
 * - `receive()` — kirim, yangi qatlam ochadi;
 * - `issue()`   — chiqim, qatlamlarni eng eskisidan sarflaydi;
 * - `reverse()` — storno, teskari yozuv (asl yozuv o'z joyida qoladi).
 *
 * Har uchtasi ham tranzaksiya ichida ishlaydi va qatlamlarni
 * `FOR UPDATE` bilan qulflaydi — ikki kassa bir vaqtda oxirgi donani
 * sotib yubormasin.
 */
final class StockLedger
{
    /**
     * Kirim — yangi FIFO qatlami ochiladi (7.20).
     */
    public function receive(
        User $author,
        Location $location,
        int $variantId,
        int $quantity,
        Money $unitCost,
        MovementType $type = MovementType::Purchase,
        ?Model $document = null,
        ?string $reason = null,
    ): StockMovement {
        $this->assertDirection($type, $quantity, 1);

        $source = $type->layerSource() ?? LayerSource::Adjustment;

        return DB::transaction(function () use (
            $author, $location, $variantId, $quantity, $unitCost, $type, $document, $reason, $source
        ): StockMovement {
            $movement = $this->writeMovement(
                $author, $location, $variantId, abs($quantity), $type,
                $unitCost->multipliedBy(abs($quantity)), false, $document, $reason,
            );

            StockLayer::create([
                'branch_id' => $location->branch_id,
                'location_id' => $location->id,
                'variant_id' => $variantId,
                'source_type' => $source,
                'source_id' => $document?->getKey(),
                'movement_id' => $movement->id,
                'unit_cost' => $unitCost->toString(),
                'quantity_in' => abs($quantity),
                'quantity_remaining' => abs($quantity),
                'received_at' => now(),
            ]);

            $this->applyToBalance($location, $variantId, abs($quantity));

            return $movement;
        });
    }

    /**
     * Chiqim — qatlamlar `received_at, id` tartibida sarflanadi.
     *
     * Ikki xil "yetishmovchilik" bir-biridan farq qiladi (7.20):
     * - **qoldiq** yetmasa → xato, salbiy qoldiqqa yo'l qo'yilmaydi;
     * - qoldiq bor, lekin **qatlam** yetmasa (odatda tizimga o'tishdagi
     *   boshlang'ich ma'lumot) → chiqim yoziladi, yetmagan qismi 0
     *   tannarx bilan ketadi va `cost_incomplete` bayrog'i qo'yiladi.
     */
    public function issue(
        User $author,
        Location $location,
        int $variantId,
        int $quantity,
        MovementType $type = MovementType::Sale,
        ?Model $document = null,
        ?string $reason = null,
    ): StockMovement {
        $this->assertDirection($type, $quantity, -1);

        $needed = $quantity;

        return DB::transaction(function () use (
            $author, $location, $variantId, $needed, $type, $document, $reason
        ): StockMovement {
            $layers = StockLayer::query()
                ->available($variantId, $location->id)
                ->lockForUpdate()
                ->get();

            $this->assertEnoughStock($location, $variantId, $needed);

            $movement = $this->writeMovement(
                $author, $location, $variantId, -$needed, $type,
                Money::zero(), false, $document, $reason,
            );

            [$cost, $incomplete] = $this->consumeLayers($layers, $movement, $needed);

            // `Immutable` UPDATE ni to'sadi — tannarx qatlamlar sarflangandan
            // keyin ma'lum bo'ladi, shuning uchun ustunlar to'g'ridan-to'g'ri
            // yoziladi. Bu yagona istisno va u shu klass ichida qoladi.
            $this->writeCost($movement, $cost, $incomplete);

            $this->applyToBalance($location, $variantId, -$needed);

            return $movement;
        });
    }

    /**
     * Storno — PROJECT.md 7.21.
     *
     * Asl yozuv **o'chirilmaydi va tahrirlanmaydi**: teskari ishorali
     * yangi harakat yoziladi, `reverses_id` orqali aslga bog'lanadi.
     *
     * - chiqim stornosi: sarflangan qatlamlarga miqdor qaytariladi va
     *   manfiy `consumption` yoziladi;
     * - kirim stornosi: shu kirim ochgan qatlamdan miqdor olib tashlanadi.
     *   Qatlamdan allaqachon sotilgan bo'lsa — storno mumkin emas,
     *   chunki sotilgan tovarning tannarxi o'zgarib ketardi.
     */
    public function reverse(User $author, StockMovement $movement, string $reason): StockMovement
    {
        if ($movement->reverses_id !== null) {
            throw ValidationException::withMessages([
                'movement' => __('warehouse::stock.reverse_of_reversal'),
            ]);
        }

        if ($movement->isReversed()) {
            throw ValidationException::withMessages([
                'movement' => __('warehouse::stock.already_reversed'),
            ]);
        }

        return DB::transaction(function () use ($author, $movement, $reason): StockMovement {
            $location = $movement->location()->firstOrFail();

            return $movement->isIncoming()
                ? $this->reverseIncoming($author, $movement, $location, $reason)
                : $this->reverseOutgoing($author, $movement, $location, $reason);
        });
    }

    /**
     * Joriy qoldiq — daftardan (kesh emas, haqiqat manbai).
     */
    public function balanceOf(int $variantId, int $locationId): int
    {
        return (int) StockMovement::withoutGlobalScopes()
            ->forVariantAt($variantId, $locationId)
            ->sum('quantity');
    }

    /**
     * @param  Collection<int, StockLayer>  $layers
     * @return array{0: Money, 1: bool}
     */
    private function consumeLayers(mixed $layers, StockMovement $movement, int $needed): array
    {
        $cost = Money::zero();
        $left = $needed;

        foreach ($layers as $layer) {
            if ($left === 0) {
                break;
            }

            $take = min($left, $layer->quantity_remaining);
            $lineCost = $layer->unit_cost->multipliedBy($take);

            StockLayerConsumption::create([
                'layer_id' => $layer->id,
                'movement_id' => $movement->id,
                'quantity' => $take,
                'unit_cost' => $layer->unit_cost->toString(),
                'total_cost' => $lineCost->toString(),
            ]);

            $layer->decrement('quantity_remaining', $take);

            $cost = $cost->plus($lineCost);
            $left -= $take;
        }

        // Qatlam yetmadi — qolgan qismi 0 tannarx bilan ketadi (7.20).
        return [$cost, $left > 0];
    }

    private function reverseIncoming(
        User $author,
        StockMovement $movement,
        Location $location,
        string $reason,
    ): StockMovement {
        $layer = $movement->layers()->lockForUpdate()->first();

        if ($layer instanceof StockLayer && $layer->quantity_remaining < $movement->quantity) {
            throw ValidationException::withMessages([
                'movement' => __('warehouse::stock.layer_partly_consumed'),
            ]);
        }

        $this->assertEnoughStock($location, $movement->variant_id, $movement->quantity);

        $reversal = $this->writeMovement(
            $author, $location, $movement->variant_id, -$movement->quantity,
            $movement->type, $movement->cost_total->negated(), $movement->cost_incomplete,
            null, $reason, $movement->id,
        );

        $layer?->decrement('quantity_remaining', $movement->quantity);

        $this->applyToBalance($location, $movement->variant_id, -$movement->quantity);

        return $reversal;
    }

    private function reverseOutgoing(
        User $author,
        StockMovement $movement,
        Location $location,
        string $reason,
    ): StockMovement {
        $reversal = $this->writeMovement(
            $author, $location, $movement->variant_id, abs($movement->quantity),
            $movement->type, $movement->cost_total->negated(), $movement->cost_incomplete,
            null, $reason, $movement->id,
        );

        foreach ($movement->consumptions()->with('layer')->get() as $consumption) {
            $layer = $consumption->layer()->lockForUpdate()->firstOrFail();
            $layer->increment('quantity_remaining', $consumption->quantity);

            StockLayerConsumption::create([
                'layer_id' => $consumption->layer_id,
                'movement_id' => $reversal->id,
                'quantity' => -$consumption->quantity,
                'unit_cost' => $consumption->unit_cost->toString(),
                'total_cost' => $consumption->total_cost->negated()->toString(),
            ]);
        }

        $this->applyToBalance($location, $movement->variant_id, abs($movement->quantity));

        return $reversal;
    }

    private function writeMovement(
        User $author,
        Location $location,
        int $variantId,
        int $quantity,
        MovementType $type,
        Money $costTotal,
        bool $costIncomplete,
        ?Model $document = null,
        ?string $reason = null,
        ?int $reversesId = null,
    ): StockMovement {
        if ($reversesId === null && $type->requiresReason() && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => __('warehouse::stock.reason_required'),
            ]);
        }

        return StockMovement::create([
            // Transit harakatida ham **jo'natuvchi** filial yoziladi
            // (ANALIZ 3.10) — location o'z filialini bilib turadi.
            'branch_id' => $location->branch_id,
            'location_id' => $location->id,
            'variant_id' => $variantId,
            'type' => $type,
            'quantity' => $quantity,
            'cost_total' => $costTotal->toString(),
            'cost_incomplete' => $costIncomplete,
            'source_type' => $document === null ? null : $document::class,
            'source_id' => $document?->getKey(),
            'reverses_id' => $reversesId,
            'reason' => $reason,
            'created_by' => $author->id,
        ]);
    }

    /**
     * Tannarx qatlamlar sarflangandan **keyin** ma'lum bo'ladi, shuning
     * uchun `Immutable` to'sig'ini chetlab, ustunlar to'g'ridan-to'g'ri
     * yoziladi. Yozuv hali shu tranzaksiya ichida va tashqariga
     * ko'rinmagan — audit izi buzilmaydi.
     */
    private function writeCost(StockMovement $movement, Money $cost, bool $incomplete): void
    {
        DB::table('stock_movements')
            ->where('id', $movement->id)
            ->update(['cost_total' => $cost->toString(), 'cost_incomplete' => $incomplete]);

        $movement->setRawAttributes([
            ...$movement->getAttributes(),
            'cost_total' => $cost->toString(),
            'cost_incomplete' => $incomplete,
        ], true);
    }

    /**
     * `receive()` va `issue()` **musbat miqdor** qabul qiladi — ishorani
     * daftar o'zi qo'yadi. Chaqiruvchi "4 dona chiqdi" deydi, "−4" emas.
     *
     * @param  int  $direction  `+1` kirim, `-1` chiqim
     */
    private function assertDirection(MovementType $type, int $quantity, int $direction): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('warehouse::stock.quantity_must_be_positive'),
            ]);
        }

        $sign = $type->fixedSign();

        if ($sign !== null && $sign !== $direction) {
            throw ValidationException::withMessages([
                'quantity' => __('warehouse::stock.wrong_sign', ['type' => $type->value]),
            ]);
        }
    }

    /**
     * Salbiy qoldiqqa yo'l qo'yilmaydi (7.20).
     */
    private function assertEnoughStock(Location $location, int $variantId, int $needed): void
    {
        $available = $this->balanceOf($variantId, $location->id);

        if ($available < $needed) {
            throw ValidationException::withMessages([
                'quantity' => __('warehouse::stock.not_enough', [
                    'available' => $available,
                    'needed' => $needed,
                ]),
            ]);
        }
    }

    /**
     * Qoldiq keshini yangilaydi (7.1).
     *
     * `ON CONFLICT ... DO UPDATE` — o'qib-yozish o'rniga bitta atomar
     * so'rov, shuning uchun parallel kirim/chiqimda qiymat yo'qolmaydi.
     */
    private function applyToBalance(Location $location, int $variantId, int $delta): void
    {
        DB::statement(
            'INSERT INTO stock_balances (location_id, variant_id, branch_id, quantity, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (location_id, variant_id)
             DO UPDATE SET quantity = stock_balances.quantity + EXCLUDED.quantity,
                           updated_at = EXCLUDED.updated_at',
            [$location->id, $variantId, $location->branch_id, $delta, now()],
        );
    }
}
