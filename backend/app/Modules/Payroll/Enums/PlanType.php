<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

/**
 * Filial reja turi — ENUMS.md §9, PROJECT.md 7.18.
 */
enum PlanType: string
{
    case Revenue = 'revenue';
    case Profit = 'profit';
    case Orders = 'orders';
}
