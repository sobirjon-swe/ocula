<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;

/**
 * Insert-only model — PROJECT.md 7.21, SCHEMA.md §0.
 *
 * Nima qiladi:
 * - `UPDATE` va `DELETE` ni model darajasida to'sadi;
 * - `updated_at` ustunini o'chiradi (bu jadvallarda u umuman yo'q).
 *
 * Yagona istisno — `stock_layers.quantity_remaining` (SCHEMA.md §3):
 * u qatlam sarflanganda o'zgaradi, shuning uchun `StockLayer` bu
 * trait'ni **ishlatmaydi**, sarflash tarixi esa
 * `stock_layer_consumptions` da to'liq saqlanadi.
 *
 * @phpstan-require-extends Model
 */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(function (Model $model): never {
            throw ImmutableRecordException::forUpdate(class_basename($model));
        });

        static::deleting(function (Model $model): never {
            throw ImmutableRecordException::forDelete(class_basename($model));
        });
    }

    /**
     * Bu jadvallarda `updated_at` ustuni yo'q (SCHEMA.md §0).
     */
    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }
}
