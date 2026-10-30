<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class ReferenceTranslationTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function service_names_are_localized_and_cyrillic_is_derived(): void
    {
        $this->seedPermissions();
        $this->actingAsDirector();
        $id = $this->postJson('/api/v1/services', ['name' => 'Tekshiruv', 'type' => 'exam', 'price' => '50000', 'name_translations' => ['uz-latn' => 'Tekshiruv', 'ru' => 'Осмотр', 'en' => 'Eye exam']])->assertCreated()->json('data.id');
        $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/services/'.$id)->assertOk()->assertJsonPath('data.name', 'Eye exam');
        $this->withHeader('Accept-Language', 'uz-cyrl')->getJson('/api/v1/services/'.$id)->assertOk()->assertJsonPath('data.name', 'Текширув');
    }
}
