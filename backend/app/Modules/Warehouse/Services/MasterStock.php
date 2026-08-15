<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Services;

use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Usta zaxirasi — PROJECT.md §6.6, 7.1, ANALIZ 3.2.
 *
 * "Usta zaxirasi" alohida jadval emas: u `master` turidagi `location`,
 * egasi — ustaning o'zi. Shuning uchun qoldiq, FIFO va hisobotlar
 * ombor bilan bir xil mexanizmdan o'tadi va "ustada nima bor" degan
 * savolga daftar javob beradi.
 *
 * Zaxiraga berish ombordan ustaga **ko'chirish**: tovar filialdan
 * chiqmaydi, faqat joyi o'zgaradi. Shu sababli ikkala harakat ham
 * `transfer_out`/`transfer_in` — filiallararo transferdagi kabi, lekin
 * hujjatsiz.
 */
final class MasterStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * Ustaning zaxira joyi — bo'lmasa ochiladi.
     *
     * Joy **filialga bog'langan**: bir usta ikki filialda ishlasa,
     * uning har filialdagi zaxirasi alohida hisoblanadi, aks holda
     * qoldiq qaysi filialniki ekani ma'lum bo'lmasdi.
     */
    public function locationFor(User $master, int $branchId): Location
    {
        $existing = Location::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('type', LocationType::Master)
            ->where('owner_type', User::class)
            ->where('owner_id', $master->id)
            ->first();

        return $existing ?? Location::create([
            'branch_id' => $branchId,
            'type' => LocationType::Master,
            'name' => __('warehouse::master_stock.location_name', ['name' => $master->name]),
            'owner_type' => User::class,
            'owner_id' => $master->id,
            'is_active' => true,
        ]);
    }

    /**
     * Ombordan usta zaxirasiga berish.
     *
     * Tannarx ombordan chiqishda FIFO dan hisoblanadi va zaxiraga
     * **o'sha qiymat** bilan kiradi: ko'chirishning o'zi foyda yoki
     * zarar yasab qo'ymasligi kerak.
     */
    public function issueTo(User $author, User $master, Location $from, int $variantId, int $quantity): Location
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('warehouse::master_stock.quantity_must_be_positive'),
            ]);
        }

        return DB::transaction(function () use ($author, $master, $from, $variantId, $quantity): Location {
            $to = $this->locationFor($master, $from->branch_id);

            $out = $this->ledger->issue(
                $author, $from, $variantId, $quantity, MovementType::TransferOut,
            );

            $this->ledger->receive(
                $author,
                $to,
                $variantId,
                $quantity,
                $out->cost_total->dividedBy($quantity),
                MovementType::TransferIn,
            );

            return $to;
        });
    }

    /**
     * Zaxiradan buyurtmaga sarflash — ANALIZ 3.4.
     *
     * `consume` ataylab `sale` dan ajratilgan: bu sotuv emas (pul
     * harakati yo'q), lekin tannarxga tushadi. Bitta turga qo'shib
     * yuborsak, savdo hisoboti ustaxona sarfini ham savdo deb
     * ko'rsatardi.
     */
    public function consume(
        User $master,
        Location $from,
        int $variantId,
        int $quantity,
        ?Model $document = null,
    ): StockMovement {
        return $this->ledger->issue(
            $master, $from, $variantId, $quantity, MovementType::Consume, $document,
        );
    }

    /**
     * Ustaning qo'lidagi qoldiq — "kimda nima bor" (§10: haydovchi
     * qatoriga o'xshash, lekin usta uchun).
     */
    public function balanceOf(User $master, int $branchId, int $variantId): int
    {
        return $this->ledger->balanceOf($variantId, $this->locationFor($master, $branchId)->id);
    }
}
