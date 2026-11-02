<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Services\DuplicateProducts;
use App\Modules\Telegram\Services\StaffNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;

final class NotifyDuplicateProducts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $period) {}

    public function handle(DuplicateProducts $duplicates, StaffNotifier $notifier): void
    {
        $pairs = $duplicates->pairs();
        if ($pairs === []) {
            return;
        }
        $preview = implode("\n", array_map(fn (array $pair): string => '#'.$pair['left_id'].' '.Str::limit($pair['left_name'], 80).' ↔ #'.$pair['right_id'].' '.Str::limit($pair['right_name'], 80), array_slice($pairs, 0, 10)));
        if (! $notifier->director('catalog-duplicates:'.$this->period, 'catalog::staff.duplicates', ['period' => $this->period, 'count' => count($pairs), 'pairs' => $preview])) {
            throw new RuntimeException('Staff notification delivery failed.');
        }
    }
}
