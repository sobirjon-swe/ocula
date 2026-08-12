<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` — PROJECT.md §9, ANALIZ 3.7.
 *
 * Chek yopish, to'lov, transfer qabul qilish kabi amallarda internet
 * uzilib qayta yuborilganda **ikkinchi hujjat yaratilmasligi** kerak.
 *
 * Oqim:
 * 1. Kalit yo'q → 400 (bu middleware faqat shunday route'larga qo'yiladi);
 * 2. Kalit yangi → qator band qilinadi, so'rov bajariladi, javob saqlanadi;
 * 3. Kalit bor + javob saqlangan → **o'sha javob qaytariladi** (qayta
 *    bajarilmaydi), `Idempotent-Replay: true` sarlavhasi bilan;
 * 4. Kalit bor + boshqa `request_hash` → **409 Conflict**;
 * 5. Kalit bor + javob hali yo'q → 409 "so'rov qayta ishlanmoqda".
 *
 * Yozuvlar 24 soatdan keyin eskiradi (`expires_at`).
 */
final class EnsureIdempotency
{
    private const string HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || trim($key) === '') {
            return ApiResponse::error(
                "Bu amal uchun '".self::HEADER."' sarlavhasi majburiy. "
                .'Har bir urinishga bitta noyob kalit yuboring.',
                status: Response::HTTP_BAD_REQUEST,
            );
        }

        $key = trim($key);
        $endpoint = $request->method().' '.$request->path();
        $hash = hash('sha256', $endpoint.'|'.$request->getContent());

        $existing = $this->claim($key, $endpoint, $hash, $request);

        if ($existing !== null) {
            return $this->replay($existing, $hash);
        }

        $response = $next($request);

        $this->store($key, $response);

        return $response;
    }

    /**
     * Kalitni band qilishga urinadi. Muvaffaqiyatli bo'lsa `null`,
     * kalit allaqachon bo'lsa — mavjud qator qaytadi.
     *
     * @return array<string, mixed>|null
     */
    private function claim(string $key, string $endpoint, string $hash, Request $request): ?array
    {
        $now = now();
        $ttlHours = (int) config('optika.idempotency.ttl_hours', 24);

        try {
            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'locked_at' => $now,
                'created_at' => $now,
                'expires_at' => $now->addHours($ttlHours),
            ]);

            return null;
        } catch (QueryException) {
            // unique(key) buzildi — demak bu takroriy so'rov.
            $row = DB::table('idempotency_keys')->where('key', $key)->first();

            return $row === null ? null : (array) $row;
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function replay(array $record, string $hash): Response
    {
        if ($record['request_hash'] !== $hash) {
            return ApiResponse::error(
                "Bu 'Idempotency-Key' boshqa so'rov uchun ishlatilgan. "
                .'Yangi amal uchun yangi kalit yuboring.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $body = $record['response_body'] ?? null;

        if ($body === null) {
            return ApiResponse::error(
                "Avvalgi so'rov hali bajarilmoqda. Bir necha soniyadan keyin qayta urinib ko'ring.",
                status: Response::HTTP_CONFLICT,
            );
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);

        return response()->json(
            $decoded,
            (int) ($record['status_code'] ?? Response::HTTP_OK),
            ['Idempotent-Replay' => 'true'],
        );
    }

    private function store(string $key, Response $response): void
    {
        $content = $response->getContent();

        DB::table('idempotency_keys')
            ->where('key', $key)
            ->update([
                'response_body' => $content === false ? null : $content,
                'status_code' => $response->getStatusCode(),
                'locked_at' => null,
            ]);
    }
}
