<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Tovar turi — ENUMS.md §2.
 *
 * Individual buyurtma linzasi (7.13) bu ro'yxatda **yo'q** — u umuman
 * `products` da yashamaydi, `order_items.custom_lens_params` da turadi.
 */
enum ProductType: string
{
    case Frame = 'frame';
    case Lens = 'lens';
    case Accessory = 'accessory';
    case Ready = 'ready';
    case Service = 'service';

    /**
     * Ombordan o'tadimi? `service` — yo'q, qolganlari — ha.
     */
    public function isStockable(): bool
    {
        return $this !== self::Service;
    }

    /**
     * Linza parametrlari (SPH/CYL/AXIS) bo'ladigan turlar (7.2).
     */
    public function hasOpticalAttributes(): bool
    {
        return $this === self::Lens;
    }
}
