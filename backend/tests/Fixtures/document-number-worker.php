<?php

declare(strict_types=1);

use App\Support\Documents\DocumentNumber;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$arguments = $_SERVER['argv'] ?? [];
$branchId = filter_var($arguments[1] ?? null, FILTER_VALIDATE_INT);
$gate = $arguments[2] ?? null;
if ($branchId === false || $branchId < 1 || ! is_string($gate)) {
    throw new InvalidArgumentException('Expected a branch ID and start barrier path.');
}
echo "READY\n";
fflush(STDOUT);
$deadline = microtime(true) + 15;
while (! file_exists($gate)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent test start barrier timed out.');
    }
    usleep(1000);
}
$numbers = [];
foreach (range(1, 20) as $i) {
    $numbers[] = DB::transaction(function () use ($branchId): string {
        $number = DocumentNumber::next('order', $branchId, 'PAR', new DateTimeImmutable('2026-10-01T00:00:00Z'));
        usleep(10000);

        return $number;
    });
}
echo json_encode(['pid' => getmypid(), 'numbers' => $numbers], JSON_THROW_ON_ERROR);
