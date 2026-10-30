<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\Storage\ImageStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImageStorageTest extends TestCase
{
    #[Test]
    public function a_real_image_is_saved_privately_with_a_generated_name(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY3sAAAAASUVORK5CYII=';
        $path = app(ImageStorage::class)->store('data:image/png;base64,'.$png, 'taxi-receipts');
        $this->assertMatchesRegularExpression('~^taxi-receipts/[a-f0-9-]+\.png$~', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame(base64_decode($png), Storage::disk('local')->get($path));
    }

    #[Test]
    public function an_image_mime_label_does_not_allow_non_image_contents(): void
    {
        Storage::fake('local');
        $this->expectException(ValidationException::class);
        app(ImageStorage::class)->store('data:image/png;base64,'.base64_encode('<script>alert(1)</script>'), 'taxi-receipts');
    }

    #[Test]
    public function oversized_uploads_are_rejected_before_decoding(): void
    {
        $this->expectException(ValidationException::class);
        app(ImageStorage::class)->store('data:image/png;base64,'.str_repeat('A', 2800001), 'taxi-receipts');
    }
}
