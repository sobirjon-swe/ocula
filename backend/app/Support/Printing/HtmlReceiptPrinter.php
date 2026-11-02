<?php

declare(strict_types=1);

namespace App\Support\Printing;

use App\Support\Contracts\ReceiptPrinter;
use App\Support\Enums\Locale;
use App\Support\Money\Money;
use App\Support\Text\Transliterator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class HtmlReceiptPrinter implements ReceiptPrinter
{
    /** @param array<string, mixed> $receipt */
    public function print(array $receipt): string
    {
        $locale = app()->getLocale();
        $labels = match ($locale) {
            'ru' => ['Внутренний чек', 'Товар', 'Кол-во', 'Сумма', 'Итого', 'Оплачено', 'Долг', 'Внутренний документ'],
            'en' => ['Internal receipt', 'Item', 'Qty', 'Amount', 'Total', 'Paid', 'Debt', 'Internal document'],
            default => ['Ichki chek', 'Tovar', 'Soni', 'Summa', 'Jami', 'To‘langan', 'Qarz', 'Ichki hujjat'],
        };
        if (app()->bound('request.locale') && app('request.locale') === Locale::UzCyrl) {
            $labels = array_map(Transliterator::toCyrillic(...), $labels);
        }
        $safe = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $value): string => Money::of(is_string($value) || is_int($value) ? $value : '0')->format();
        $html = '<!doctype html><html lang="'.$safe($locale).'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safe($receipt['number'] ?? '').'</title><style>body{font:15px/1.5 system-ui;max-width:420px;margin:24px auto;padding:16px;color:#171717}h1{font-size:22px;margin:0}table{width:100%;border-collapse:collapse;margin:20px 0}td,th{text-align:left;padding:8px 0;border-bottom:1px solid #ddd}td:last-child,th:last-child{text-align:right}dl{display:grid;grid-template-columns:1fr auto;gap:8px}dd{margin:0;text-align:right}footer{margin-top:24px;font-size:12px}@media print{body{margin:0;max-width:none}}</style><body><h1>OPTIKA</h1><p>'.$safe($labels[0]).' '.$safe($receipt['number'] ?? '').'<br>'.$safe($receipt['branch'] ?? '').'<br>'.$safe(now()->timezone('Asia/Tashkent')->format('d.m.Y H:i')).'</p><table><thead><tr><th>'.$safe($labels[1]).'</th><th>'.$safe($labels[2]).'</th><th>'.$safe($labels[3]).'</th></tr></thead><tbody>';
        $items = $receipt['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item)) {
                    $html .= '<tr><td>'.$safe($item['name'] ?? '').'</td><td>'.$safe($item['quantity'] ?? '').'</td><td>'.$safe($money($item['total'] ?? '0')).'</td></tr>';
                }
            }
        }
        $html .= '</tbody></table><dl>';
        foreach (['total' => 4, 'paid' => 5, 'debt' => 6] as $field => $label) {
            $html .= '<dt>'.$safe($labels[$label]).'</dt><dd>'.$safe($money($receipt[$field] ?? '0')).'</dd>';
        }
        $html .= '</dl><footer>'.$safe($labels[7]).'</footer></body></html>';
        $path = 'receipts/'.Str::uuid().'.html';
        Storage::disk('local')->put($path, $html);

        return $path;
    }

    public function isFiscal(): bool
    {
        return false;
    }
}
