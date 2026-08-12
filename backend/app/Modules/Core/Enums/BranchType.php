<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Filial turi — ENUMS.md §1.
 */
enum BranchType: string
{
    /** Asosiy filial (A) — markaziy ombor, ustaxona va shifokor shu yerda. */
    case Main = 'main';

    /** Oddiy savdo nuqtasi (B, C, D, E). */
    case Shop = 'shop';
}
