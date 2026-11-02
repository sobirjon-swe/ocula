<?php

declare(strict_types=1);
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) < 4) {
    exit(2);
}
$variantId = (int) $arguments[1];
$location = Location::withoutGlobalScopes()->findOrFail((int) $arguments[2]);
$user = User::findOrFail((int) $arguments[3]);
echo DB::selectOne('SELECT pg_backend_pid() AS id')->id."\n";
flush();
app(StockLedger::class)->receive($user, $location, $variantId, 1, Money::of('500'));
echo "DONE\n";
