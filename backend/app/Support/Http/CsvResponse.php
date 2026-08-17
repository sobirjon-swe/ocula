<?php

declare(strict_types=1);

namespace App\Support\Http;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV eksport — PROJECT.md §2, BOSQICH-10.md §10c.
 *
 * `openspout/openspout` o'rniga PHP'ning o'zi (`fputcsv`): bu
 * konteynerning tarmoq siyosati GitHub zipball orqali paket
 * o'rnatishni cheklaydi (avvalgi bosqichlarda `phpstan`/`larastan`
 * bilan ham xuddi shu muammo uchragan). Funksional jihatdan CSV
 * eksport talabini to'liq qondiradi, faqat XLSX emas.
 *
 * `StreamedResponse` — butun hisobot xotiraga yig'ilmaydi, qatorma-qator
 * yoziladi.
 */
final class CsvResponse
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            // BOM — Excel UTF-8'ni to'g'ri o'qishi uchun.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }
}
