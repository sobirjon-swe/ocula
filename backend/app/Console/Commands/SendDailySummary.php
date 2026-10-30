<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Analytics\Services\CashReportService;
use App\Modules\Analytics\Services\ProfitReportService;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Telegram\Services\TelegramClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SendDailySummary extends Command
{
    protected $signature = 'telegram:daily-summary {--date= : Business date YYYY-MM-DD}';

    protected $description = 'Send a deduplicated shift-based daily summary to the director';

    public function handle(TelegramClient $telegram, CashReportService $cash, ProfitReportService $profit): int
    {
        $chat = config('services.telegram.director_chat_id');
        if (! $chat) {
            $this->warn('TELEGRAM_DIRECTOR_CHAT_ID is not configured.');

            return self::SUCCESS;
        }
        $now = CarbonImmutable::now('Asia/Tashkent');
        $dateText = $this->option('date') ?: ($now->hour < 3 ? $now->subDay() : $now)->toDateString();
        if (! is_string($dateText) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateText)) {
            $this->error('Use --date=YYYY-MM-DD');

            return self::FAILURE;
        }
        $date = CarbonImmutable::parse($dateText, 'Asia/Tashkent');
        $director = User::query()->role('director')->where('is_active', true)->first();
        if (! $director instanceof User) {
            $this->error('Create an active director first.');

            return self::FAILURE;
        }
        if (DB::table('shifts')->where('status', 'open')->whereBetween('opened_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()])->exists()) {
            $this->info('Shifts are still open. The next scheduled run will retry.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($chat, $date, $director, $cash, $profit, $telegram): void {
            DB::table('operational_reports')->insertOrIgnore(['date' => $date->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            $record = DB::table('operational_reports')->where('date', $date->toDateString())->lockForUpdate()->first();
            if ($record->sent_at !== null) {
                return;
            }
            $rows = [];
            $text = 'OPTIKA · '.$date->format('d.m.Y');
            foreach (Branch::query()->where('is_active', true)->orderBy('code')->get() as $branch) {
                $money = $cash->generate($director, $date, $date, $branch->id);
                $sales = $profit->generate($director, $date, $date, $branch->id);
                $rows[] = ['branch' => $branch->code, 'cash' => $money['net_total'], 'profit' => $sales['profit']];
                $text .= "\n".$branch->code.': Kassa '.$money['net_total'].' · Foyda '.$sales['profit'].' UZS';
            }
            $telegram->sendMessage((int) $chat, $text);
            DB::table('operational_reports')->where('date', $date->toDateString())->update(['payload' => json_encode($rows, JSON_THROW_ON_ERROR), 'sent_at' => now(), 'updated_at' => now()]);
        });

        return self::SUCCESS;
    }
}
