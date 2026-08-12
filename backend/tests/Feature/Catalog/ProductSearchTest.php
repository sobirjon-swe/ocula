<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Katalog qidiruvi — PROJECT.md 7.13, §10, ANALIZ 3.14.
 *
 * Sotuvchi "Рэй бан" deb yozsa ham, "Ray Ban" deb yozsa ham bitta
 * tovarni topishi kerak — aks holda katalogda dublikat paydo bo'ladi.
 */
final class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_fills_the_search_key_on_save(): void
    {
        $product = Product::factory()->create(['name' => 'Ray-Ban 3025 qora']);

        $this->assertSame('rayban3025qora', $product->search_key);
    }

    #[Test]
    public function it_updates_the_search_key_when_the_name_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Hoya 1.61']);

        $product->update(['name' => 'Essilor 1.67']);

        $this->assertSame('essilor167', $product->fresh()?->search_key);
    }

    #[Test]
    public function it_finds_a_latin_product_by_a_cyrillic_query(): void
    {
        Product::factory()->create(['name' => 'Rey Ban 3025']);

        $found = Product::query()->search('Рэй Бан')->get();

        $this->assertCount(1, $found);
    }

    #[Test]
    public function it_finds_a_product_written_with_a_hyphen(): void
    {
        Product::factory()->create(['name' => 'Ray-Ban 3025']);

        $this->assertCount(1, Product::query()->search('ray ban 3025')->get());
    }

    #[Test]
    public function it_ignores_an_empty_query(): void
    {
        Product::factory()->count(3)->create();

        // Bo'sh so'rov filtrni qo'llamaydi — hammasi qaytadi.
        $this->assertCount(3, Product::query()->search('...')->get());
    }

    /**
     * 7.17 — tasdiqlash savdoni to'smaydi: `pending` tovar ham sotiladi.
     */
    #[Test]
    public function pending_products_remain_sellable(): void
    {
        Product::factory()->quickCreated()->create(['name' => 'Yangi ramka']);
        Product::factory()->create([
            'name' => 'Rad etilgan',
            'status' => ProductStatus::Rejected,
        ]);

        $sellable = Product::query()->sellable()->get();

        $this->assertCount(1, $sellable);
        $this->assertSame('Yangi ramka', $sellable->first()?->name);
        $this->assertCount(1, Product::query()->pending()->get());
    }
}
