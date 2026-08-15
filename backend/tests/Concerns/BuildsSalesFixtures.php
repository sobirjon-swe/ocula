<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Service;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Savdo testlari uchun umumiy sahna: filial, ombor, narxi qo'yilgan
 * variant va ombordagi qoldiq.
 *
 * Qoldiq **`StockLedger` orqali** yaratiladi, jadvalga to'g'ridan-to'g'ri
 * yozib emas: aks holda test FIFO qatlamlarisiz qoldiq yasab, sotuvda
 * tannarx 0 chiqqanini sezmay qolardi.
 */
trait BuildsSalesFixtures
{
    protected Branch $branch;

    protected Location $warehouse;

    protected ProductVariant $variant;

    protected User $author;

    protected function setUpSalesFixtures(string $price = '250000'): void
    {
        $this->branch = Branch::factory()->main()->create(['code' => 'A']);
        $this->warehouse = Location::factory()->for($this->branch)
            ->create(['type' => LocationType::Warehouse]);

        $this->author = User::factory()->for($this->branch)->create();

        $this->variant = ProductVariant::factory()->for(
            Product::factory()->create(['created_by' => $this->author->id])
        )->create();

        $this->setPrice($price);
    }

    /**
     * Global narx (filial narxi yo'q) — ANALIZ 3.16.
     */
    protected function setPrice(string $price, ?ProductVariant $variant = null): Price
    {
        return Price::factory()->create([
            'variant_id' => ($variant ?? $this->variant)->id,
            'branch_id' => null,
            'price' => $price,
            'created_by' => $this->author->id,
        ]);
    }

    /**
     * Omborga kirim — bitta FIFO qatlami ochiladi.
     */
    protected function stockUp(int $quantity, string $unitCost = '75000'): void
    {
        app(StockLedger::class)->receive(
            $this->author,
            $this->warehouse,
            $this->variant->id,
            $quantity,
            Money::of($unitCost),
        );
    }

    protected function balance(): int
    {
        return app(StockLedger::class)->balanceOf($this->variant->id, $this->warehouse->id);
    }

    protected function makeService(string $price = '50000'): Service
    {
        return Service::factory()->create(['price' => $price]);
    }

    /**
     * Amalni bajaruvchi so'rovlar `Idempotency-Key` talab qiladi (§9).
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    protected function postAction(string $uri, array $payload = []): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($uri, $payload);
    }

    /**
     * Tez savdo cheki: yaratiladi va darrov topshiriladi.
     *
     * @param  array<int, array<string, mixed>>|null  $items
     * @return array<string, mixed>
     */
    protected function quickSalePayload(?array $items = null, int $quantity = 2): array
    {
        return [
            'branch_id' => $this->branch->id,
            'type' => 'quick',
            'items' => $items ?? [
                ['kind' => 'variant', 'id' => $this->variant->id, 'quantity' => $quantity],
            ],
        ];
    }
}
