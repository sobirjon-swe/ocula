<?php

declare(strict_types=1);

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ImageStorage
{
    public function store(string $dataUrl, string $folder, string $field = 'photo'): string
    {
        if (strlen($dataUrl) > 2800000 || ! preg_match('/^data:image\/(jpeg|png|webp);base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            $this->invalid($field);
        }
        $bytes = base64_decode($matches[2], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] * $info[1] > 16000000) {
            $this->invalid($field);
        }
        $extension = match ($info['mime']) {
            'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
        };
        $path = trim($folder, '/').'/'.Str::uuid().'.'.$extension;
        if (! Storage::put($path, $bytes)) {
            throw new \RuntimeException('Image storage is unavailable.');
        }

        return $path;
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => __('validation.image', ['attribute' => $field])]);
    }
}
