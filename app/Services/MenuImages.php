<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menu item photos. An uploaded picture is re-drawn (when PHP's GD is available) at no more than 900 px on the
 * longest side and saved as WebP/JPEG under a random name, so the café's pages stay light, camera metadata is
 * dropped, and nothing but a plain image can reach the public folder. Files live on the "public" disk
 * (storage/app/public/menu, linked to public/storage by `php artisan storage:link`).
 */
class MenuImages
{
    public const MAX_SIDE = 900;

    public const DIRECTORY = 'menu';

    /** Saves the upload and returns its path on the public disk, e.g. "menu/Qk3….webp". */
    public function store(UploadedFile $file): string
    {
        $redrawn = $this->redraw($file);

        if ($redrawn) {
            [$bytes, $extension] = $redrawn;
            $path = self::DIRECTORY.'/'.Str::random(40).'.'.$extension;
            Storage::disk('public')->put($path, $bytes);

            return $path;
        }

        // No GD: keep the file as it came, under a random name with the extension its content proves
        return $file->storeAs(self::DIRECTORY, Str::random(40).'.'.($file->guessExtension() ?: 'jpg'), 'public');
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /** The address the browser loads the photo from, or null when the item has none. */
    public static function url(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }

    /** @return array{0: string, 1: string}|null the picture's bytes and file extension */
    private function redraw(UploadedFile $file): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            // Whatever the file claims to be, if it can't be read as a picture it is not stored at all
            throw ValidationException::withMessages(['image' => 'That file could not be read as a picture. Choose a JPG, PNG or WebP.']);
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $image = $scale < 1 ? imagescale($source, (int) round($width * $scale), (int) round($height * $scale)) : $source;

        imagesavealpha($image, true);
        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($image, null, 82);
            $extension = 'webp';
        } else {
            imagejpeg($image, null, 85);
            $extension = 'jpg';
        }
        $bytes = (string) ob_get_clean();

        return $bytes === '' ? null : [$bytes, $extension];
    }
}
