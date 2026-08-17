<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * Yo'qotilgan savdo sababi — SCHEMA.md `lost_sales`, PROJECT.md 7.9.
 */
enum LostSaleReason: string
{
    /** Tovar katalogda bor, lekin qoldiq 0. */
    case OutOfStock = 'out_of_stock';

    /** Tovar katalogda umuman yo'q. */
    case NotInCatalog = 'not_in_catalog';

    /** Mijoz narxdan voz kechdi. */
    case Price = 'price';
}
