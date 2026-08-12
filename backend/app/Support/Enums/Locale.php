<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Interfeys tillari — PROJECT.md §10, ENUMS.md §1.
 *
 * **`uz-cyrl` qo'lda tarjima qilinmaydi** — `uz-latn` dan
 * `App\Support\Text\Transliterator` orqali avtomatik hosil bo'ladi.
 * Ya'ni amalda 3 ta tarjima fayli yuritiladi.
 */
enum Locale: string
{
    case UzLatn = 'uz-latn';
    case UzCyrl = 'uz-cyrl';
    case Ru = 'ru';
    case En = 'en';

    public static function default(): self
    {
        return self::UzLatn;
    }

    /**
     * Tarjima fayli bor tillar. `uz-cyrl` bu ro'yxatda yo'q — u hosila.
     *
     * @return array<int, self>
     */
    public static function translated(): array
    {
        return [self::UzLatn, self::Ru, self::En];
    }

    /**
     * Transliteratsiya orqali hosil bo'ladigan tilmi?
     */
    public function isDerived(): bool
    {
        return $this === self::UzCyrl;
    }

    /**
     * Tarjima fayllari qaysi til ostida yotadi.
     */
    public function sourceLocale(): self
    {
        return $this->isDerived() ? self::UzLatn : $this;
    }

    /**
     * O'z tilida nomi (til tanlash ro'yxati uchun).
     */
    public function label(): string
    {
        return match ($this) {
            self::UzLatn => "O'zbekcha",
            self::UzCyrl => 'Ўзбекча',
            self::Ru => 'Русский',
            self::En => 'English',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
