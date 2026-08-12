<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * Insert-only yozuvni o'zgartirishga urinish — PROJECT.md 7.21.
 *
 * `stock_movements`, `stock_layer_consumptions`, `payments`,
 * `cash_movements`, `supplier_transactions`, `bonus_entries` —
 * bu jadvallar tahrirlanmaydi va o'chirilmaydi. Xato bo'lsa **storno**:
 * teskari ishorali yangi yozuv + `reverses_id` + `reason`.
 */
final class ImmutableRecordException extends RuntimeException
{
    public static function forUpdate(string $model): self
    {
        return new self(
            "{$model} yozuvi tahrirlanmaydi (7.21). Tuzatish uchun storno yozuvi yarating."
        );
    }

    public static function forDelete(string $model): self
    {
        return new self(
            "{$model} yozuvi o'chirilmaydi (7.21). Tuzatish uchun storno yozuvi yarating."
        );
    }
}
