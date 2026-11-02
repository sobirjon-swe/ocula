<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Actions\Shift\OpenShift;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\StockLayer;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

// Run through stdin in the built app image; never copied into production images.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('OPTIKA_SMOKE') !== '1' || config('database.connections.pgsql.database') !== 'optika_ci') {
    throw new RuntimeException('Smoke fixtures require the explicit optika_ci database and OPTIKA_SMOKE=1.');
}
$mode = $argv[1] ?? '';
if (! in_array($mode, ['seed', 'verify'], true)) {
    throw new RuntimeException('Expected seed or verify.');
}
$branch = Branch::withoutGlobalScopes()->where('code', 'A')->firstOrFail();
$location = $branch->warehouse() ?? throw new RuntimeException('Seeded branch warehouse missing.');
if ($mode === 'seed') {
    if (User::withoutGlobalScopes()->exists()) {
        throw new RuntimeException('Smoke seed requires a fresh database with no employees.');
    }
    $user = User::create(['name' => 'CI smoke', 'phone' => '+998900000001', 'password' => Hash::make(bin2hex(random_bytes(24))), 'branch_id' => $branch->id, 'locale' => 'en', 'is_active' => true]);
    $user->assignRole('director');
    Auth::login($user);
    $product = Product::create(['name' => 'CI FIFO fixture', 'type' => 'frame', 'unit' => 'dona', 'status' => 'approved', 'is_active' => true, 'created_by' => $user->id]);
    $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CI-FIFO', 'is_active' => true]);
    $ledger = app(StockLedger::class);
    $ledger->receive($user, $location, $variant->id, 3, Money::of('100'), MovementType::Adjustment, reason: 'CI opening layer');
    $ledger->receive($user, $location, $variant->id, 2, Money::of('300'), MovementType::Adjustment, reason: 'CI second layer');
    $issued = $ledger->issue($user, $location, $variant->id, 4, MovementType::WriteOff, reason: 'CI FIFO check');
    if ($issued->cost_total->toString() !== '600.00') {
        throw new RuntimeException('FIFO issue did not consume the expected 600.00 cost.');
    }
    $shift = app(OpenShift::class)->handle($user, $branch->id, Money::of('1000'));
    app(CashRegister::class)->record($user, $branch->id, CashCategory::Collection, Money::of('500'), $shift, description: 'CI restore cash');
    if (! Storage::disk('local')->put('ci-smoke-private.txt', 'private restore sentinel') || ! Storage::disk('public')->put('ci-smoke-public.txt', 'public restore sentinel')) {
        throw new RuntimeException('Attachment fixture could not be saved.');
    }
}
$variant = ProductVariant::query()->where('sku', 'CI-FIFO')->firstOrFail();
$shift = Shift::withoutGlobalScopes()->where('branch_id', $branch->id)->firstOrFail();
$snapshot = [
    'balance' => app(StockLedger::class)->balanceOf($variant->id, $location->id),
    'movement_quantity' => (int) StockMovement::withoutGlobalScopes()->where('variant_id', $variant->id)->sum('quantity'),
    'remaining_cost' => Money::of((string) StockLayer::withoutGlobalScopes()->where('variant_id', $variant->id)->selectRaw('SUM(quantity_remaining * unit_cost) AS total')->value('total'))->toString(),
    'cash' => app(CashRegister::class)->expectedCash($shift)->toString(),
    'private_file' => Storage::disk('local')->get('ci-smoke-private.txt'),
    'public_file' => Storage::disk('public')->get('ci-smoke-public.txt'),
];
$expected = ['balance' => 1, 'movement_quantity' => 1, 'remaining_cost' => '300.00', 'cash' => '1500.00', 'private_file' => 'private restore sentinel', 'public_file' => 'public restore sentinel'];
if ($snapshot !== $expected) {
    throw new RuntimeException('Restored domain snapshot mismatch: '.json_encode($snapshot, JSON_THROW_ON_ERROR));
}
echo json_encode($snapshot, JSON_THROW_ON_ERROR).PHP_EOL;
