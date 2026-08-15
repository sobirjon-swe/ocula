<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Enums\CashDirection;
use App\Modules\Finance\Models\CashMovement;
use App\Support\Contracts\CashLedger;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Kassa daftari — PROJECT.md 7.21, SCHEMA.md §8.
 *
 * Bu klass **`cash_movements` ga yozadigan yagona joy**, xuddi
 * `StockLedger` ombor uchun bo'lgani kabi. Kontroller ham, Action ham
 * jadvalga to'g'ridan-to'g'ri yozmaydi: aks holda seyfdagi pul bilan
 * daftar ajralib ketardi va "kim yozdi" degan savolga javob qolmasdi.
 *
 * Ikkita amal:
 * - `record()`  — yangi yozuv (yo'nalishni toifa belgilaydi);
 * - `reverse()` — storno, asl yozuv o'z joyida qoladi.
 *
 * Naqd bo'lmagan to'lovlar (karta, o'tkazma) bu yerga **umuman
 * kelmaydi** — ular kassaga tushmaydi (ENUMS.md §4).
 */
final class CashRegister implements CashLedger
{
    /**
     * Kassaga yozuv qo'shish.
     *
     * `$direction` faqat `correction` toifasi uchun kerak — qolganlarida
     * yo'nalish toifaning o'zidan kelib chiqadi va uni tashqaridan
     * o'zgartirib bo'lmaydi.
     */
    public function record(
        User $author,
        int $branchId,
        CashCategory $category,
        Money $amount,
        ?Shift $shift = null,
        ?Model $source = null,
        ?string $description = null,
        ?CashDirection $direction = null,
    ): CashMovement {
        if (! $amount->isPositive()) {
            throw ValidationException::withMessages([
                'amount' => __('finance::cash.amount_must_be_positive'),
            ]);
        }

        $resolved = $category->direction() ?? $direction;

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'type' => __('finance::cash.direction_required'),
            ]);
        }

        return CashMovement::create([
            'branch_id' => $branchId,
            'shift_id' => $shift->id ?? $this->openShiftId($branchId),
            'type' => $resolved,
            'category' => $category,
            'amount' => $amount->toString(),
            'source_type' => $source === null ? null : $source::class,
            'source_id' => $source?->getKey(),
            'description' => $description,
            'created_by' => $author->id,
        ]);
    }

    /**
     * Storno — PROJECT.md 7.21.
     *
     * Asl yozuv o'chirilmaydi va tahrirlanmaydi: teskari yo'nalishli
     * yangi yozuv qo'yiladi va `reverses_id` orqali aslga bog'lanadi.
     * Toifa `correction` bo'ladi (ENUMS.md §8) — kunlik hisobotda
     * "tuzatish" alohida qator bo'lib ko'rinsin.
     *
     * `shift_opening` storno qilinmaydi: boshlang'ich naqd smenaning
     * o'z ustunida turadi, uni tuzatish daftardan emas, smenadan
     * boshlanishi kerak.
     */
    public function reverse(User $author, CashMovement $movement, string $reason): CashMovement
    {
        if ($movement->category === CashCategory::ShiftOpening) {
            throw ValidationException::withMessages([
                'movement' => __('finance::cash.shift_opening_not_reversible'),
            ]);
        }

        if ($movement->reverses_id !== null) {
            throw ValidationException::withMessages([
                'movement' => __('finance::cash.reverse_of_reversal'),
            ]);
        }

        if ($movement->isReversed()) {
            throw ValidationException::withMessages([
                'movement' => __('finance::cash.already_reversed'),
            ]);
        }

        return CashMovement::create([
            'branch_id' => $movement->branch_id,
            'shift_id' => $movement->shift_id,
            'type' => $movement->type->opposite(),
            'category' => CashCategory::Correction,
            'amount' => $movement->amount->toString(),
            'source_type' => $movement->source_type,
            'source_id' => $movement->source_id,
            'reverses_id' => $movement->id,
            'reason' => $reason,
            'created_by' => $author->id,
        ]);
    }

    /**
     * Smena bo'yicha kutilgan naqd — PROJECT.md 5.2.
     *
     * `shift_opening` ataylab chiqarib tashlanadi: u `opening_cash`
     * ning daftardagi ko'rinishi, ikkalasi qo'shilsa boshlang'ich naqd
     * ikki marta sanalardi.
     */
    public function expectedCash(Shift $shift): Money
    {
        return $shift->opening_cash->plus($this->netFor($shift));
    }

    public function recordShiftOpening(User $author, Shift $shift, Money $amount): void
    {
        if (! $amount->isPositive()) {
            return;
        }

        $this->record(
            $author, $shift->branch_id, CashCategory::ShiftOpening, $amount, $shift, $shift,
        );
    }

    public function recordShortage(User $author, Shift $shift, Money $amount): void
    {
        $this->record(
            $author, $shift->branch_id, CashCategory::Shortage, $amount, $shift, $shift,
        );
    }

    public function recordSurplus(User $author, Shift $shift, Money $amount): void
    {
        $this->record(
            $author, $shift->branch_id, CashCategory::Surplus, $amount, $shift, $shift,
        );
    }

    /**
     * Filialdagi joriy naqd — ochiq smena bo'yicha.
     */
    public function currentBalance(Shift $shift): Money
    {
        return $this->expectedCash($shift);
    }

    /**
     * Smena harakatlarining ishorali yig'indisi (`shift_opening` siz).
     *
     * Global scope ataylab o'chirilgan: direktor boshqa filial smenasini
     * yopishi mumkin, o'shanda ham daftar to'liq sanalishi kerak.
     */
    private function netFor(Shift $shift): Money
    {
        $total = CashMovement::query()
            ->withoutGlobalScopes()
            ->forShift($shift->id)
            ->where('category', '!=', CashCategory::ShiftOpening->value)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS net',
                [CashDirection::In->value],
            )
            ->value('net');

        return Money::of((string) ($total ?? '0'));
    }

    /**
     * Yozuvni ochiq smenaga bog'laydi. Smena ochilmagan bo'lsa `null` —
     * yozuv baribir qoladi (smenadan tashqari xarajat ham bo'ladi), lekin
     * u smena hisobiga tushmaydi.
     */
    private function openShiftId(int $branchId): ?int
    {
        $shift = Shift::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->active()
            ->latest('opened_at')
            ->first();

        return $shift?->id;
    }
}
