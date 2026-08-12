<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * API karkasi tirikmi — PROJECT.md §9.
 *
 * Backend faqat JSON qaytaradi, Blade shabloni yo'q (§15 #15).
 */
final class HealthCheckTest extends TestCase
{
    #[Test]
    public function the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function the_api_ping_returns_the_data_envelope(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['service', 'version', 'time']]);
    }

    #[Test]
    public function unknown_api_routes_return_json_not_html(): void
    {
        $response = $this->getJson('/api/v1/yoq-endpoint');

        $response->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
