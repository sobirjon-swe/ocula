<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Database\Seeder;

/** Optional demonstrator data. Never part of production or the default empty catalog. */
class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo scenarios are available only in local/testing.');
        }
        $author = User::query()->role('director')->firstOrFail();
        $branch = Branch::query()->where('code', 'A')->firstOrFail();
        foreach ([['DEMO-001', 'Demo ramka', 'frame', '300000', '150000'], ['DEMO-002', 'Demo linza', 'lens', '120000', '60000']] as [$sku, $name, $type, $price, $cost]) {
            $product = Product::query()->firstOrCreate(['name' => $name], ['type' => $type, 'unit' => 'dona', 'status' => 'approved', 'is_active' => true, 'created_by' => $author->id]);
            $variant = ProductVariant::query()->firstOrCreate(['sku' => $sku], ['product_id' => $product->id, 'barcode' => $sku === 'DEMO-001' ? '20260001' : '20260002', 'is_active' => true]);
            Price::query()->firstOrCreate(['variant_id' => $variant->id, 'branch_id' => null], ['price' => $price, 'valid_from' => now(), 'created_by' => $author->id]);
            $location = $branch->warehouse();
            if ($location !== null && app(StockLedger::class)->balanceOf($variant->id, $location->id) === 0) {
                app(StockLedger::class)->receive($author, $location, $variant->id, 10, Money::of($cost), MovementType::Adjustment, reason: 'Demo opening stock');
            }
        }
        Customer::query()->firstOrCreate(['phone' => '+998900000099'], ['name' => 'Demo mijoz', 'locale' => 'uz-latn']);
    }
}
