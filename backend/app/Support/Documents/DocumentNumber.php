<?php

declare(strict_types=1);

namespace App\Support\Documents;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hujjat raqami generatsiyasi — SCHEMA.md §1, ANALIZ 3.12.
 *
 * Format: `{BRANCH_CODE}-{YY}{MM}-{NNNNN}` → `A-2608-00147`.
 *
 * Har filial + hujjat turi + oy uchun alohida hisoblagich
 * (`document_sequences`). Parallel so'rovlarda raqam takrorlanmasligi
 * uchun qator `lockForUpdate()` bilan bloklanadi — bu **tranzaksiya
 * ichida** chaqirilishi shart (§14 dagi majburiy test).
 *
 * Support qatlami modullardan mustaqil bo'lishi uchun bu klass `Branch`
 * modelini bilmaydi — filial `id` va `code` sifatida beriladi.
 */
final class DocumentNumber
{
    /** Hisoblagich uzunligi: `00147`. */
    private const int PADDING = 5;

    /**
     * Keyingi raqamni oladi va hisoblagichni oshiradi.
     *
     * @param  string  $type  `order` | `transfer` | `purchase` | `inventory` | `return`
     */
    public static function next(
        string $type,
        int $branchId,
        string $branchCode,
        ?DateTimeInterface $at = null,
    ): string {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                'DocumentNumber::next() tranzaksiya ichida chaqirilishi shart — '
                ."aks holda parallel so'rovlarda raqam takrorlanadi (ANALIZ 3.12)."
            );
        }

        $moment = $at ?? now();
        $period = $moment->format('ym');

        $sequence = self::lockRow($branchId, $type, $period);

        if ($sequence === null) {
            // Birinchi hujjat: qatorni yaratamiz. Parallel urinishda
            // unique(branch_id, type, period) ishlaydi — o'shanda qayta o'qiymiz.
            DB::table('document_sequences')->insertOrIgnore([
                'branch_id' => $branchId,
                'type' => $type,
                'period' => $period,
                'last_number' => 0,
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);

            $sequence = self::lockRow($branchId, $type, $period);
        }

        if ($sequence === null) {
            throw new RuntimeException(
                "document_sequences qatorini yaratib bo'lmadi: {$branchCode}/{$type}/{$period}."
            );
        }

        $nextNumber = (int) $sequence['last_number'] + 1;

        DB::table('document_sequences')
            ->where('id', $sequence['id'])
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => $moment,
            ]);

        return self::format($branchCode, $period, $nextNumber);
    }

    /**
     * Raqamni yig'adi: `A` + `2608` + `00147` → `A-2608-00147`.
     */
    public static function format(string $branchCode, string $period, int $number): string
    {
        return sprintf(
            '%s-%s-%s',
            strtoupper($branchCode),
            $period,
            str_pad((string) $number, self::PADDING, '0', STR_PAD_LEFT),
        );
    }

    /**
     * Qatorni bloklab o'qiydi. `stdClass` o'rniga massiv qaytariladi —
     * ustunlar ro'yxati statik tahlilda ma'lum emas.
     *
     * @return array<string, mixed>|null
     */
    private static function lockRow(int $branchId, string $type, string $period): ?array
    {
        $row = DB::table('document_sequences')
            ->where('branch_id', $branchId)
            ->where('type', $type)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : (array) $row;
    }
}
