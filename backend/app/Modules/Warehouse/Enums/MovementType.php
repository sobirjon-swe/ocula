<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Ombor harakati turi — ENUMS.md §3, PROJECT.md 7.1, 7.20.
 *
 * Qoldiq hech qachon jadvalda raqam bo'lib turmaydi — u shu yozuvlar
 * yig'indisi (7.1). Har bir tur FIFO qatlamlariga qanday ta'sir
 * qilishini `layerEffect()` aytadi.
 */
enum MovementType: string
{
    /** Yetkazib beruvchidan kirim — qatlam ochadi. */
    case Purchase = 'purchase';

    /** Sotildi — qatlam sarflaydi. */
    case Sale = 'sale';

    /** Filialdan chiqdi (yo'lga) — sarflaydi. */
    case TransferOut = 'transfer_out';

    /** Filialga kirdi (yo'ldan) — sarflangan tannarx bilan ochadi. */
    case TransferIn = 'transfer_in';

    /** Inventarizatsiya ortiqchasi yoki qo'lda tuzatish — ishorasiga qarab. */
    case Adjustment = 'adjustment';

    /**
     * Inventarizatsiya kamomadi.
     *
     * `adjustment` dan ataylab ajratilgan: hisobotda "kamomad" va
     * "qo'lda tuzatish" bir qatorga tushmasligi kerak (ENUMS.md §3).
     */
    case WriteOff = 'write_off';

    /** Brak (7.5) — sarflaydi. */
    case Defect = 'defect';

    /** Mijozdan qaytdi — sotilgan tannarx bilan ochadi. */
    case Return = 'return';

    /** Usta o'z zaxirasidan buyurtmaga ishlatdi — sarflaydi. */
    case Consume = 'consume';

    /** Dublikat tovar birlashtirilganda qoldiqni ko'chirish (7.17). */
    case Merge = 'merge';

    /**
     * Miqdor ishorasi qat'iy belgilanganmi va qanday.
     *
     * `null` — ikkala ishora ham mumkin (`adjustment`, `merge`).
     */
    public function fixedSign(): ?int
    {
        return match ($this) {
            self::Purchase, self::TransferIn, self::Return => 1,
            self::Sale, self::TransferOut, self::WriteOff, self::Defect, self::Consume => -1,
            self::Adjustment, self::Merge => null,
        };
    }

    /**
     * Bu tur uchun `reason` majburiymi.
     *
     * Qo'lda tuzatish va kamomad izohsiz qolsa, hisobotda "nega" degan
     * savolga javob bo'lmaydi (7.1).
     */
    public function requiresReason(): bool
    {
        return match ($this) {
            self::Adjustment, self::WriteOff, self::Defect => true,
            default => false,
        };
    }

    /**
     * Qatlam manbai — kirim bo'lganda qanday `LayerSource` ochiladi.
     */
    public function layerSource(): ?LayerSource
    {
        return match ($this) {
            self::Purchase => LayerSource::Purchase,
            self::TransferIn => LayerSource::TransferIn,
            self::Return => LayerSource::Return,
            self::Adjustment => LayerSource::Adjustment,
            self::Merge => LayerSource::Merge,
            default => null,
        };
    }
}
