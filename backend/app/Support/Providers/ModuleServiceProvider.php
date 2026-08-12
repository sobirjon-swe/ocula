<?php

declare(strict_types=1);

namespace App\Support\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Modul uchun umumiy provider — PROJECT.md §3, ANALIZ 0.6.
 *
 * Har bir modul (`app/Modules/<Nom>`) shundan meros oladi va o'zining
 * `Routes/api.php`, `Lang/`, `Policies/` fayllarini avtomatik ulaydi.
 *
 * **Migratsiyalar modul ichida emas** — ular markazlashgan holda
 * `database/migrations/` da yashaydi, chunki jadvallar orasidagi tashqi
 * kalitlar aniq tartibni talab qiladi (SCHEMA.md §12).
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Modul nomi — `Core`, `Catalog`, `Warehouse`…
     */
    abstract protected function moduleName(): string;

    /**
     * Model → Policy juftliklari.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    public function boot(): void
    {
        $this->bootRoutes();
        $this->bootTranslations();
        $this->bootPolicies();
    }

    /**
     * `Routes/api.php` → `/api/v1/*`, `api` middleware guruhi bilan.
     */
    protected function bootRoutes(): void
    {
        $routes = $this->modulePath('Routes/api.php');

        if (! is_file($routes)) {
            return;
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group($routes);
    }

    /**
     * `Lang/` → `__('core::messages.shift_closed')` kabi murojaat.
     */
    protected function bootTranslations(): void
    {
        $lang = $this->modulePath('Lang');

        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, strtolower($this->moduleName()));
        }
    }

    protected function bootPolicies(): void
    {
        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    protected function modulePath(string $append = ''): string
    {
        $base = app_path('Modules/'.$this->moduleName());

        return $append === '' ? $base : $base.DIRECTORY_SEPARATOR.$append;
    }
}
