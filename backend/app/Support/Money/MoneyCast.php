<?php

declare(strict_types=1);

namespace App\Support\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * `decimal(15,2)` ustunini `Money` ga o'raydi — PROJECT.md §15 #18.
 *
 * Ishlatilishi:
 *
 *     protected function casts(): array
 *     {
 *         return ['total' => MoneyCast::class];
 *     }
 *
 * @implements CastsAttributes<Money, Money|string|int>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return self::toMoney($value, $key);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::toMoney($value, $key)?->toString();
    }

    private static function toMoney(mixed $value, string $key): ?Money
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return Money::of($value);
        }

        throw new InvalidArgumentException(
            "'{$key}' ustuniga pul qiymati sifatida faqat Money, string yoki int berilishi mumkin."
        );
    }
}
