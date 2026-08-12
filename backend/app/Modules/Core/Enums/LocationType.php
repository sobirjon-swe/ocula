<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Qoldiq "joyi" — PROJECT.md 7.1, ENUMS.md §1.
 *
 * `location` filialdan kengroq tushuncha: tovar yo'lda ham, usta
 * zaxirasida ham turishi mumkin.
 */
enum LocationType: string
{
    /** Filial ombori — 1-versiyada yagona ishlatiladigan tur (§15 #22). */
    case Warehouse = 'warehouse';

    /** Savdo zali — sxemada bor, 1-versiyada yaratilmaydi. */
    case Floor = 'floor';

    /** "Yo'lda" — egasi haydovchi/xodim (`User`) yoki transferning o'zi (7.10). */
    case Transit = 'transit';

    /** Usta zaxirasi — egasi usta (`User`). */
    case Master = 'master';

    /**
     * Har filialda bittadan bo'lishi shart bo'lgan turlar (partial unique index).
     *
     * @return array<int, self>
     */
    public static function singletonPerBranch(): array
    {
        return [self::Warehouse, self::Floor];
    }

    /** Egasi bo'ladigan turlar polimorf `owner_type`/`owner_id` ishlatadi (3.2). */
    public function requiresOwner(): bool
    {
        return $this === self::Transit || $this === self::Master;
    }
}
