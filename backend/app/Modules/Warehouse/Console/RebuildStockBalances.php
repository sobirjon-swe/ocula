<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Qoldiq keshini daftardan qayta hisoblaydi — PROJECT.md 7.1.
 *
 * `stock_balances` faqat hosila: haqiqat manbai `stock_movements`.
 * Kesh va daftar ajralib qolsa (masalan qo'lda SQL aralashuvidan keyin),
 * bu buyruq keshni daftarga moslaydi — teskarisi emas.
 *
 * Butun jadval bitta tranzaksiyada almashtiriladi, shuning uchun ish
 * o'rtasida hech kim yarim yangilangan qoldiqni ko'rmaydi.
 */
final class RebuildStockBalances extends Command
{
    protected $signature = 'stock:rebuild-balances {--dry-run : Faqat farqni ko\'rsatadi, yozmaydi}';

    protected $description = 'stock_balances keshini stock_movements daftaridan qayta hisoblaydi';

    public function handle(): int
    {
        $expected = DB::table('stock_movements')
            ->selectRaw('location_id, variant_id, branch_id, SUM(quantity)::int AS quantity')
            ->groupBy('location_id', 'variant_id', 'branch_id')
            ->get();

        if ($this->option('dry-run')) {
            return $this->reportDifferences($expected);
        }

        DB::transaction(function () use ($expected): void {
            DB::table('stock_balances')->delete();

            $now = now();
            $rows = $expected->map(static fn (stdClass $row): array => [
                'location_id' => $row->location_id,
                'variant_id' => $row->variant_id,
                'branch_id' => $row->branch_id,
                'quantity' => $row->quantity,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('stock_balances')->insert($chunk);
            }
        });

        $this->info("Qoldiq keshi qayta hisoblandi: {$expected->count()} qator.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, stdClass>  $expected
     */
    private function reportDifferences(Collection $expected): int
    {
        /** @var Collection<string, stdClass> $cached */
        $cached = DB::table('stock_balances')
            ->get()
            ->keyBy(static fn (stdClass $row): string => $row->location_id.':'.$row->variant_id);

        $differences = [];

        foreach ($expected as $row) {
            $key = $row->location_id.':'.$row->variant_id;
            $cachedRow = $cached->get($key);
            $have = $cachedRow === null ? 0 : (int) $cachedRow->quantity;

            if ($have !== (int) $row->quantity) {
                $differences[] = [$key, $have, (int) $row->quantity];
            }

            $cached->forget($key);
        }

        // Daftarda umuman yo'q, lekin keshda osilib qolgan qatorlar.
        foreach ($cached as $key => $row) {
            $differences[] = [$key, (int) $row->quantity, 0];
        }

        if ($differences === []) {
            $this->info('Kesh daftarga mos — farq yo\'q.');

            return self::SUCCESS;
        }

        $this->warn(count($differences).' ta farq topildi:');
        $this->table(['location:variant', 'keshda', 'daftarda'], $differences);

        return self::FAILURE;
    }
}
