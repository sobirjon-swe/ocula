<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yagona javob formati — PROJECT.md §9.
 *
 *     { "data": {...}, "meta": {...} }          // muvaffaqiyat
 *     { "message": "...", "errors": {...} }     // xato
 *
 * Xato xabari **nima qilish kerakligini** aytadi, aybdor qidirmaydi (§10):
 * ✅ "Ombordan chiqarib bo'lmadi: bu tovardan 2 dona qoldi"
 * ❌ "Xatolik yuz berdi"
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, array $meta = [], int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function created(mixed $data, array $meta = []): JsonResponse
    {
        return self::data($data, $meta, Response::HTTP_CREATED);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function error(
        string $message,
        array $errors = [],
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
