<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Support\Contracts\ReceiptPrinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReceiptPrinterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_bound_internal_printer_escapes_user_text_and_produces_a_printable_document(): void
    {
        Storage::fake('local');
        $printer = app(ReceiptPrinter::class);
        $path = $printer->print(['number' => 'A-2610-00001', 'branch' => 'A', 'items' => [['name' => '<script>alert(1)</script>', 'quantity' => 1, 'total' => '100000.00']], 'total' => '100000.00', 'paid' => '100000.00', 'debt' => '0.00']);
        $html = Storage::disk('local')->get($path);
        $this->assertFalse($printer->isFiscal());
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('100 000', str_replace("\u{00a0}", ' ', $html));
    }
}
