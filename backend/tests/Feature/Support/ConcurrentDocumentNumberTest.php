<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Core\Models\Branch;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ConcurrentDocumentNumberTest extends TestCase
{
    // Child connections must see committed fixtures; RefreshDatabase wraps
    // them in a parent-only transaction and would invalidate this test.
    use DatabaseMigrations;

    #[Test]
    public function simultaneous_processes_create_the_first_sequence_and_issue_unique_numbers(): void
    {
        $branch = Branch::factory()->create(['code' => 'PAR']);
        $gate = sys_get_temp_dir().'/ocula-number-gate-'.bin2hex(random_bytes(12));
        $connection = config('database.connections.pgsql');
        $workers = [];
        try {
            foreach (range(1, 4) as $i) {
                $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/document-number-worker.php'), (string) $branch->id, $gate], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql',
                    'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
                    'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
                    'DB_PASSWORD' => $connection['password'],
                ]);
                $worker->setTimeout(30)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            do {
                $ready = array_filter($workers, fn (Process $worker): bool => str_contains($worker->getOutput(), "READY\n"));
                if (count($ready) === 4) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the concurrent start barrier: '.implode('\n', array_map(fn (Process $worker): string => $worker->getErrorOutput(), $workers)));
                }
                usleep(1000);
            } while (true);
            file_put_contents($gate, 'start');
            $numbers = [];
            $pids = [];
            foreach ($workers as $worker) {
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $output = json_decode(substr($worker->getOutput(), strlen("READY\n")), true, flags: JSON_THROW_ON_ERROR);
                $pids[] = $output['pid'];
                array_push($numbers, ...$output['numbers']);
            }
            sort($numbers);
            $this->assertCount(4, array_unique($pids));
            $this->assertSame(array_map(fn (int $number): string => sprintf('PAR-2610-%05d', $number), range(1, 80)), $numbers);
            $this->assertSame(80, DB::table('document_sequences')->where('branch_id', $branch->id)->value('last_number'));
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (is_file($gate)) {
                unlink($gate);
            }
        }
    }
}
