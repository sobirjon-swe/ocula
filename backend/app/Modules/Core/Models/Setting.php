<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\SettingKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Direktor o'zgartira oladigan sozlamalar — SCHEMA.md §1.
 *
 * `config/optika.php` standart qiymatni beradi, bu jadval esa uni
 * ustidan yopadi. Sozlanadigan kalitlar ro'yxati — `SettingKey` enumi.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property int|null $updated_by
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
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Sozlamani oladi; jadvalda bo'lmasa `config/optika.php` dagi qiymat.
     *
     * Kod shu metodni chaqiradi, `config()` ni to'g'ridan-to'g'ri emas —
     * aks holda direktor interfeysdan o'zgartirgan qiymat e'tiborsiz
     * qolardi.
     */
    public static function valueFor(SettingKey $key): mixed
    {
        $row = static::query()->where('key', $key->value)->first();

        return $row instanceof self ? $row->value : config($key->configPath());
    }

    /**
     * Barcha sozlanadigan kalitlar va ularning joriy qiymati.
     *
     * @return array<string, mixed>
     */
    public static function resolved(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();

        $resolved = [];

        foreach (SettingKey::cases() as $key) {
            $resolved[$key->value] = array_key_exists($key->value, $stored)
                ? $stored[$key->value]
                : config($key->configPath());
        }

        return $resolved;
    }

    /**
     * Sozlamani yozadi (yoki yangilaydi).
     */
    public static function put(SettingKey $key, mixed $value, ?int $editorId = null): self
    {
        /** @var self $setting */
        $setting = static::query()->updateOrCreate(
            ['key' => $key->value],
            ['value' => $value, 'updated_by' => $editorId],
        );

        return $setting;
    }
}
