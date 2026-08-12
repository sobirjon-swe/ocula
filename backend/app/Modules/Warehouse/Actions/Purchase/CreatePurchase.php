<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Purchase;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Models\Purchase;
use App\Support\Documents\DocumentNumber;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kirim hujjatini yaratish — SCHEMA.md §3.
 *
 * Ombor **tegilmaydi**: hujjat `draft` bo'lib tug'iladi, qatlamlar
 * faqat qabul qilinganda ochiladi (`ReceivePurchase`).
 *
 * Raqam `DocumentNumber` orqali — u tranzaksiya ichida qulflab
 * beriladi, shuning uchun butun yaratish bitta tranzaksiyada.
 */
final class CreatePurchase
{
    /**
     * @param  array<int, array{variant_id: int, quantity: int, cost_price: string}>  $items
     */
    public function handle(User $author, Branch $branch, Location $location, array $items, string $date, ?int $supplierId = null, ?string $note = null): Purchase
    {
        if ($location->branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'location_id' => __('warehouse::purchase.location_not_in_branch'),
            ]);
        }

        return DB::transaction(function () use ($author, $branch, $location, $items, $date, $supplierId, $note): Purchase {
            $purchase = Purchase::create([
                'supplier_id' => $supplierId,
                'branch_id' => $branch->id,
                'location_id' => $location->id,
                'number' => DocumentNumber::next('purchase', $branch->id, $branch->code),
                'date' => $date,
                'status' => PurchaseStatus::Draft,
                'note' => $note,
                'created_by' => $author->id,
            ]);

            $total = Money::zero();

            foreach ($items as $row) {
                $cost = Money::of($row['cost_price']);
                $line = $cost->multipliedBy($row['quantity']);

                $purchase->items()->create([
                    'variant_id' => $row['variant_id'],
                    'quantity' => $row['quantity'],
                    'cost_price' => $cost->toString(),
                    'total' => $line->toString(),
                ]);

                $total = $total->plus($line);
            }

            $purchase->update(['total' => $total->toString()]);

            return $purchase;
        });
    }
}
