<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Policies\BrandPolicy;
use App\Modules\Catalog\Policies\CategoryPolicy;
use App\Modules\Catalog\Policies\PricePolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Catalog\Policies\ProductVariantPolicy;
use App\Modules\Catalog\Policies\ServicePolicy;
use App\Support\Providers\ModuleServiceProvider;

/**
 * Catalog — tovarlar, variantlar, narxlar, brendlar, kategoriyalar,
 * xizmatlar. PROJECT.md §6.2, 7.13, 7.17.
 */
final class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Catalog';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Brand::class => BrandPolicy::class,
            Category::class => CategoryPolicy::class,
            Service::class => ServicePolicy::class,
            Product::class => ProductPolicy::class,
            ProductVariant::class => ProductVariantPolicy::class,
            Price::class => PricePolicy::class,
        ];
    }
}
