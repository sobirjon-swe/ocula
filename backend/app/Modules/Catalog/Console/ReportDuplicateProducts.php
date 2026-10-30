<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Jobs\NotifyDuplicateProducts;
use Illuminate\Console\Command;

final class ReportDuplicateProducts extends Command
{
    protected $signature = 'catalog:report-duplicates';

    protected $description = 'Queue the weekly probable catalog duplicate review for the director';

    public function handle(): int
    {
        if (config('services.telegram.director_chat_id')) {
            NotifyDuplicateProducts::dispatch(now('Asia/Tashkent')->format('o-\WW'))->afterCommit();
        }

        return self::SUCCESS;
    }
}
