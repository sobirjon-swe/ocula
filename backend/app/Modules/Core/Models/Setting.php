<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Direktor o'zgartira oladigan sozlamalar — SCHEMA.md §1.
 *
 * `config/optika.php` standart qiymatni beradi, bu jadval esa uni
 * ustidan yopadi (`min_prepayment_percent`, `money_rounding_step`,
 * `prescription_validity_months`, `debt_reminder_days`).
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected $fillable = ['key', 'value', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * Sozlamani oladi; jadvalda bo'lmasa `config/optika.php` dagi qiymat.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        if ($row === null) {
            return $default;
        }

        return $row->value;
    }
}
