<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\LayerSource;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\StockLayer;
use App\Modules\Warehouse\Models\StockLayerConsumption;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Exceptions\ImmutableRecordException;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ombor daftari va FIFO tannarxi — PROJECT.md 7.1, 7.20, 7.21.
 *
 * Bu loyihadagi eng muhim testlar: foyda hisoboti, mukofot, brak zarari
 * va ABC tahlil — hammasi shu yerdagi tannarxga tayanadi.
 */
final class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private StockLedger $ledger;

    private User $author;

    private Location $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StockLedger::class);

        $branch = Branch::factory()->main()->create();
        $this->author = User::factory()->for($branch)->create();
        $this->warehouse = Location::factory()->for($branch)->create(['type' => LocationType::Warehouse]);

        $product = Product::factory()->create(['created_by' => $this->author->id]);
        $this->variant = ProductVariant::factory()->for($product)->create();
    }

    #[Test]
    public function a_receipt_opens_a_layer_and_raises_the_balance(): void
    {
        $movement = $this->receive(10, '50000');

        $this->assertSame(10, $movement->quantity);
        $this->assertSame('500000.00', $movement->cost_total->toString());

        $layer = StockLayer::firstOrFail();
        $this->assertSame(10, $layer->quantity_in);
        $this->assertSame(10, $layer->quantity_remaining);
        $this->assertSame(LayerSource::Purchase, $layer->source_type);
        $this->assertSame($movement->id, $layer->movement_id);

        $this->assertSame(10, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));
        $this->assertDatabaseHas('stock_balances', [
            'location_id' => $this->warehouse->id,
            'variant_id' => $this->variant->id,
            'quantity' => 10,
        ]);
    }

    /**
     * FIFO ning butun ma'nosi: chiqim **eng eski** qatlamdan ketadi,
     * shuning uchun sotilgan tovarning tannarxi eski narx bo'yicha.
     */
    #[Test]
    public function an_issue_consumes_the_oldest_layer_first(): void
    {
        $this->receive(10, '50000');
        $this->travelSeconds();
        $this->receive(10, '60000');

        $movement = $this->issue(4);

        $this->assertSame(-4, $movement->quantity);
        $this->assertSame('200000.00', $movement->cost_total->toString());
        $this->assertFalse($movement->cost_incomplete);

        $layers = StockLayer::orderBy('id')->get();
        $this->assertSame(6, $layers[0]->quantity_remaining);
        $this->assertSame(10, $layers[1]->quantity_remaining);
    }

    #[Test]
    public function an_issue_spanning_two_layers_mixes_their_costs(): void
    {
        $this->receive(10, '50000');
        $this->travelSeconds();
        $this->receive(10, '60000');

        // 10 × 50 000 + 2 × 60 000 = 620 000
        $movement = $this->issue(12);

        $this->assertSame('620000.00', $movement->cost_total->toString());

        $layers = StockLayer::orderBy('id')->get();
        $this->assertSame(0, $layers[0]->quantity_remaining);
        $this->assertSame(8, $layers[1]->quantity_remaining);

        $this->assertSame(2, StockLayerConsumption::where('movement_id', $movement->id)->count());
    }

    #[Test]
    public function the_movement_cost_equals_the_sum_of_its_consumptions(): void
    {
        $this->receive(5, '30000');
        $this->travelSeconds();
        $this->receive(5, '40000');

        $movement = $this->issue(7);

        $sum = StockLayerConsumption::where('movement_id', $movement->id)->sum('total_cost');
        $this->assertSame($movement->cost_total->toString(), Money::of((string) $sum)->toString());
    }

    /**
     * Salbiy qoldiqqa yo'l qo'yilmaydi (7.20) — "tovar bor, lekin
     * tizimda yo'q" holatining teskarisi ham xuddi shunday yomon.
     */
    #[Test]
    public function it_refuses_to_issue_more_than_the_balance(): void
    {
        $this->receive(3, '50000');

        try {
            $this->issue(4);
            $this->fail('Qoldiqdan ortiq chiqim o\'tib ketdi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $this->assertSame(3, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));
        $this->assertSame(1, StockMovement::count());
    }

    /**
     * Qoldiq bor, lekin qatlam yo'q — chiqim o'tadi, yetmagan qismi
     * 0 tannarx bilan ketadi va bayroq qo'yiladi (7.20).
     */
    #[Test]
    public function a_movement_without_layers_is_flagged_as_cost_incomplete(): void
    {
        // Boshlang'ich ma'lumot xatosini taqlid qilamiz: daftarda kirim
        // bor, qatlam esa yo'q.
        StockMovement::create([
            'branch_id' => $this->warehouse->branch_id,
            'location_id' => $this->warehouse->id,
            'variant_id' => $this->variant->id,
            'type' => MovementType::Adjustment,
            'quantity' => 5,
            'cost_total' => '0.00',
            'created_by' => $this->author->id,
            'reason' => 'Boshlang\'ich qoldiq',
        ]);

        $movement = $this->issue(2);

        $this->assertSame('0.00', $movement->cost_total->toString());
        $this->assertTrue($movement->cost_incomplete);
        $this->assertSame(0, StockLayerConsumption::where('movement_id', $movement->id)->count());
    }

    #[Test]
    public function a_partially_covered_issue_is_also_flagged(): void
    {
        $this->receive(2, '50000');

        // Qatlamsiz qo'shimcha qoldiq.
        StockMovement::create([
            'branch_id' => $this->warehouse->branch_id,
            'location_id' => $this->warehouse->id,
            'variant_id' => $this->variant->id,
            'type' => MovementType::Adjustment,
            'quantity' => 3,
            'cost_total' => '0.00',
            'created_by' => $this->author->id,
            'reason' => 'Boshlang\'ich qoldiq',
        ]);

        $movement = $this->issue(5);

        $this->assertSame('100000.00', $movement->cost_total->toString());
        $this->assertTrue($movement->cost_incomplete);
    }

    #[Test]
    public function a_movement_can_not_be_edited_or_deleted(): void
    {
        $movement = $this->receive(5, '50000');

        $this->expectException(ImmutableRecordException::class);
        $movement->update(['quantity' => 99]);
    }

    #[Test]
    public function a_movement_can_not_be_deleted(): void
    {
        $movement = $this->receive(5, '50000');

        $this->expectException(ImmutableRecordException::class);
        $movement->delete();
    }

    #[Test]
    public function a_consumption_can_not_be_edited(): void
    {
        $this->receive(5, '50000');
        $this->issue(2);

        $consumption = StockLayerConsumption::firstOrFail();

        $this->expectException(ImmutableRecordException::class);
        $consumption->update(['quantity' => 99]);
    }

    /**
     * Storno — asl yozuv joyida qoladi, teskarisi qo'shiladi (7.21).
     */
    #[Test]
    public function reversing_an_issue_returns_the_quantity_to_its_layers(): void
    {
        $this->receive(10, '50000');
        $issue = $this->issue(4);

        $reversal = $this->ledger->reverse($this->author, $issue, 'Kassir adashdi');

        $this->assertSame(4, $reversal->quantity);
        $this->assertSame($issue->id, $reversal->reverses_id);
        $this->assertSame('-200000.00', $reversal->cost_total->toString());

        // Asl yozuv o'chirilmagan.
        $this->assertDatabaseHas('stock_movements', ['id' => $issue->id, 'quantity' => -4]);

        $this->assertSame(10, StockLayer::firstOrFail()->quantity_remaining);
        $this->assertSame(10, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));

        // Manfiy sarflash yozuvi qo'shilgan, asl sarflash o'chmagan.
        $this->assertSame(2, StockLayerConsumption::count());
        $this->assertSame(-4, StockLayerConsumption::where('movement_id', $reversal->id)->sum('quantity'));
    }

    #[Test]
    public function reversing_a_receipt_removes_it_from_the_layer(): void
    {
        $receipt = $this->receive(10, '50000');

        $reversal = $this->ledger->reverse($this->author, $receipt, 'Hujjat xato kiritilgan');

        $this->assertSame(-10, $reversal->quantity);
        $this->assertSame(0, StockLayer::firstOrFail()->quantity_remaining);
        $this->assertSame(0, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));
    }

    /**
     * Kirimdan tovar chiqib ketgan bo'lsa storno mumkin emas — aks holda
     * allaqachon sotilgan tovarning tannarxi o'zgarib ketardi.
     */
    #[Test]
    public function a_receipt_can_not_be_reversed_once_it_has_been_issued_from(): void
    {
        $receipt = $this->receive(10, '50000');
        $this->issue(1);

        $this->expectException(ValidationException::class);
        $this->ledger->reverse($this->author, $receipt, 'Hujjat xato');
    }

    #[Test]
    public function a_movement_can_not_be_reversed_twice(): void
    {
        $issue = $this->issue(2, prepared: 5);
        $this->ledger->reverse($this->author, $issue, 'Birinchi storno');

        $this->expectException(ValidationException::class);
        $this->ledger->reverse($this->author, $issue, 'Ikkinchi storno');
    }

    #[Test]
    public function a_reversal_can_not_itself_be_reversed(): void
    {
        $issue = $this->issue(2, prepared: 5);
        $reversal = $this->ledger->reverse($this->author, $issue, 'Storno');

        $this->expectException(ValidationException::class);
        $this->ledger->reverse($this->author, $reversal, 'Stornoning stornosi');
    }

    #[Test]
    public function an_adjustment_requires_a_reason(): void
    {
        $this->expectException(ValidationException::class);

        $this->ledger->receive(
            $this->author, $this->warehouse, $this->variant->id, 5,
            Money::of('50000'), MovementType::Adjustment,
        );
    }

    /**
     * Miqdor doim musbat — ishorani daftar o'zi qo'yadi.
     */
    #[Test]
    public function a_negative_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->ledger->receive(
            $this->author, $this->warehouse, $this->variant->id, -5,
            Money::of('50000'), MovementType::Purchase,
        );
    }

    /**
     * `sale` faqat chiqim bo'la oladi — uni kirim sifatida yozib
     * bo'lmaydi, aks holda daftar ma'nosini yo'qotadi.
     */
    #[Test]
    public function a_type_that_can_only_go_one_way_is_rejected_in_the_other(): void
    {
        $this->expectException(ValidationException::class);

        $this->ledger->receive(
            $this->author, $this->warehouse, $this->variant->id, 5,
            Money::of('50000'), MovementType::Sale,
        );
    }

    /**
     * `adjustment` ikkala yo'nalishda ham bo'la oladi (ENUMS.md §3).
     */
    #[Test]
    public function an_adjustment_works_in_both_directions(): void
    {
        $this->ledger->receive(
            $this->author, $this->warehouse, $this->variant->id, 5,
            Money::of('50000'), MovementType::Adjustment, null, 'Inventarizatsiya ortiqchasi',
        );

        $this->ledger->issue(
            $this->author, $this->warehouse, $this->variant->id, 2,
            MovementType::Adjustment, null, 'Qo\'lda tuzatish',
        );

        $this->assertSame(3, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));
    }

    /**
     * `stock_balances` faqat kesh — daftardan har doim qayta
     * hisoblanadi (7.1).
     */
    #[Test]
    public function the_cached_balance_always_matches_the_ledger(): void
    {
        $this->receive(10, '50000');
        $this->issue(3);
        $this->receive(5, '55000');
        $this->issue(2);

        $cached = (int) DB::table('stock_balances')
            ->where('location_id', $this->warehouse->id)
            ->where('variant_id', $this->variant->id)
            ->value('quantity');

        $this->assertSame(10, $cached);
        $this->assertSame(10, $this->ledger->balanceOf($this->variant->id, $this->warehouse->id));
    }

    private function receive(int $quantity, string $unitCost): StockMovement
    {
        return $this->ledger->receive(
            $this->author,
            $this->warehouse,
            $this->variant->id,
            $quantity,
            Money::of($unitCost),
        );
    }

    private function issue(int $quantity, ?int $prepared = null): StockMovement
    {
        if ($prepared !== null) {
            $this->receive($prepared, '50000');
        }

        return $this->ledger->issue(
            $this->author,
            $this->warehouse,
            $this->variant->id,
            $quantity,
        );
    }

    /**
     * Qatlamlar `received_at` bo'yicha tartiblanadi — ikki kirim bir xil
     * soniyaga tushib qolmasin.
     */
    private function travelSeconds(int $seconds = 2): void
    {
        $this->travel($seconds)->seconds();
    }
}
