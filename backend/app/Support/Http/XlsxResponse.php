<?php

declare(strict_types=1);

namespace App\Support\Http;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class XlsxResponse
{
    /** @param array<int,array<string,mixed>> $rows */
    public static function download(string $name, array $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'optika-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create export temporary file.');
        }
        $writer = new Writer;
        $writer->openToFile($path);
        $headers = $rows === [] ? [] : array_keys($rows[0]);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $values = array_map(static function (mixed $value): string|int|float|bool|null {
                if (is_string($value) && preg_match('/^[=+@-]/', $value) && ! is_numeric($value)) {
                    return "'".$value;
                }

                return is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR);
            }, array_values($row));
            $writer->addRow(Row::fromValues($values));
        }
        $writer->close();

        return response()->download($path, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
