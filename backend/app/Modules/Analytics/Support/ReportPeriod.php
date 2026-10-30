<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ReportPeriod
{
    /** Dates are store business dates; records attached to a shift follow its opening day. */
    public static function apply(Builder $query, string $table, string $timestamp, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $start = CarbonImmutable::parse($from->toDateString(), 'Asia/Tashkent')->startOfDay()->utc();
        $end = CarbonImmutable::parse($to->toDateString(), 'Asia/Tashkent')->endOfDay()->utc();

        return $query->where(function (Builder $q) use ($table, $timestamp, $start, $end): void {
            $q->whereIn($table.'.shift_id', DB::table('shifts')->whereBetween('opened_at', [$start, $end])->select('id'))
                ->orWhere(fn (Builder $q) => $q->whereNull($table.'.shift_id')->whereBetween($timestamp, [$start, $end]));
        });
    }
}
