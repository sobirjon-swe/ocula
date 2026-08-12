<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureFactories();
    }

    /**
     * Modellar `App\Modules\<Modul>\Models\` da yashaydi (PROJECT.md §3),
     * factory'lar esa markazlashgan holda `Database\Factories\` da.
     *
     * Laravel'ning standart taxmini `Database\Factories\Modules\Core\Models\
     * BranchFactory` ni qidiradi — shuning uchun qoidani soddalashtiramiz:
     * model nomi + `Factory`.
     */
    protected function configureFactories(): void
    {
        Factory::guessFactoryNamesUsing(
            /**
             * @param  class-string<Model>  $model
             * @return class-string<Factory<Model>>
             */
            static function (string $model): string {
                $factory = 'Database\\Factories\\'.class_basename($model).'Factory';

                if (! is_subclass_of($factory, Factory::class)) {
                    throw new InvalidArgumentException(
                        "{$model} uchun factory topilmadi: {$factory} mavjud emas."
                    );
                }

                return $factory;
            },
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
