<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\Enums\Locale;
use App\Support\Text\Transliterator;

trait HasLocalizedName
{
    public function localizedName(): string
    {
        $locale = app()->bound('request.locale') ? app('request.locale') : Locale::tryFrom(app()->getLocale());
        $locale = $locale instanceof Locale ? $locale : Locale::default();
        $translations = $this->getAttribute('name_translations');
        $text = is_array($translations) ? ($translations[$locale->sourceLocale()->value] ?? $this->getAttribute('name')) : $this->getAttribute('name');
        $text = is_string($text) ? $text : '';

        return $locale->isDerived() ? Transliterator::toCyrillic($text) : $text;
    }
}
