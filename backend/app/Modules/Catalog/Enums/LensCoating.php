<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Linza qoplamasi — ENUMS.md §2.
 */
enum LensCoating: string
{
    case None = 'none';
    case Hmc = 'hmc';
    case Shmc = 'shmc';
    case Blue = 'blue';
    case Photochromic = 'photochromic';
    case Polarized = 'polarized';
    case Mirror = 'mirror';
}
