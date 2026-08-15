<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Transfer;

use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Models\Transfer;
use Illuminate\Validation\ValidationException;

/**
 * Miqdor farqini hal qilish — PROJECT.md 7.4, PERMISSIONS.md §3.
 *
 * Bu amal **omborga tegmaydi**: yetmagan tovar qabul qilish paytida
 * allaqachon hisobdan chiqarilgan. Bu yerda yopiladigan narsa — ochiq
 * savol: direktor farqni ko'rib chiqdi va xulosasini yozdi.
 *
 * Holat `partially_received` bo'lib **qoladi**: transfer haqiqatan kam
 * kelgan, buni keyin `received` ga aylantirib qo'yish tarixni
 * bo'yardi. O'zgaradigani — `has_discrepancy` bayrog'i, ya'ni
 * "direktorga signal" o'chadi.
 */
final class ResolveDiscrepancy
{
    public function handle(User $resolver, Transfer $transfer, string $resolution): Transfer
    {
        if (! $transfer->has_discrepancy) {
            throw ValidationException::withMessages([
                'transfer' => __('warehouse::transfer.no_discrepancy'),
            ]);
        }

        $transfer->update([
            'has_discrepancy' => false,
            'note' => $this->appendResolution($transfer, $resolver, $resolution),
        ]);

        return $transfer;
    }

    /**
     * Xulosa hujjat izohiga qo'shiladi, ustidan yozilmaydi — avvalgi
     * izoh ham kimgadir kerak bo'lgan.
     */
    private function appendResolution(Transfer $transfer, User $resolver, string $resolution): string
    {
        $line = __('warehouse::transfer.discrepancy_resolved', [
            'name' => $resolver->name,
            'reason' => $resolution,
        ]);

        return $transfer->note === null || trim($transfer->note) === ''
            ? $line
            : $transfer->note."\n".$line;
    }
}
