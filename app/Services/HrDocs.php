<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared bits of the HR documents: the settings printed on ID cards and
 * letters, and saving employee photos / the signature (kept in private
 * storage, served through signed-in or signed routes only).
 */
class HrDocs
{
    public const DEFAULTS = [
        'phone' => null,
        'office_phone' => '09614-552233',
        'email' => 'sunlitnetwork@gmail.com',
        'website' => 'www.sunlitnetwork.com',
        'address' => 'Roshid Super Market 2nd Floor, Navaron Bazar, Sharsha, Jashore',
        'theme' => 'navy',
        'signatory' => 'Md Habibur Rahman',
        'signatory_title' => 'Chairman',
        'signature' => null,
        'validity_years' => 2,
    ];

    /**
     * Card templates: [label, dark, mid, accent, layout]. Layouts: wave
     * (dark lower half with curves), angle (light, diagonal bands), classic
     * (dark header with the photo on its edge). The first four keep their
     * old keys so saved cards look the same.
     */
    public const THEMES = [
        'navy' => ['Wave · Sunlit blue', '#0a2a5e', '#123f86', '#1e88e5', 'wave'],
        'teal' => ['Wave · Teal', '#063f3c', '#0b5f5a', '#14b8a6', 'wave'],
        'maroon' => ['Wave · Maroon', '#4a0d1a', '#701428', '#e11d48', 'wave'],
        'graphite' => ['Wave · Graphite', '#111827', '#1f2937', '#f59e0b', 'wave'],
        'wave_green' => ['Wave · Green', '#064e3b', '#065f46', '#10b981', 'wave'],
        'wave_purple' => ['Wave · Purple', '#2e1065', '#4c1d95', '#8b5cf6', 'wave'],
        'angle_blue' => ['Diagonal · Blue', '#0b3b8c', '#1d4ed8', '#38bdf8', 'angle'],
        'angle_green' => ['Diagonal · Green', '#14532d', '#15803d', '#4ade80', 'angle'],
        'angle_orange' => ['Diagonal · Orange', '#7c2d12', '#c2410c', '#fb923c', 'angle'],
        'angle_purple' => ['Diagonal · Purple', '#4c1d95', '#6d28d9', '#c084fc', 'angle'],
        'classic_navy' => ['Classic · Navy', '#0f2557', '#1e3a8a', '#f59e0b', 'classic'],
        'classic_red' => ['Classic · Red', '#7f1d1d', '#991b1b', '#fbbf24', 'classic'],
        'classic_teal' => ['Classic · Teal', '#134e4a', '#0f766e', '#5eead4', 'classic'],
        'classic_black' => ['Classic · Black & gold', '#0b0b0b', '#262626', '#d4a017', 'classic'],
    ];

    public function settings(): array
    {
        return AppSetting::get('hr', self::DEFAULTS);
    }

    public function theme(?string $key = null): array
    {
        $key ??= $this->settings()['theme'];

        return self::THEMES[$key] ?? self::THEMES['navy'];
    }

    /**
     * Save a photo for the employee: a data: URL from the cropper or an
     * uploaded file. Square-cropped to 600 px JPEG; the old one is removed.
     */
    public function storePhoto(Employee $employee, string|UploadedFile $source): void
    {
        $employee->forceFill(['photo' => $this->savePhoto($source, $employee->photo)])->save();
    }

    /** Square 600 px JPEG in private storage; returns its path and removes $old. */
    public function savePhoto(string|UploadedFile $source, ?string $old = null): string
    {
        $bytes = $source instanceof UploadedFile ? $source->get() : $this->fromDataUrl($source);
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'That photo could not be read — use a JPG or PNG.']);
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);
        $out = imagecreatetruecolor(600, 600);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        // Centre crop; portraits keep a bit more of the top (the face).
        $x = (int) (($w - $side) / 2);
        $y = $h > $w ? (int) (($h - $side) * 0.25) : 0;
        imagecopyresampled($out, $image, 0, 0, $x, $y, 600, 600, $side, $side);

        ob_start();
        imagejpeg($out, null, 88);
        $jpeg = ob_get_clean();

        $path = 'employees/' . Str::random(32) . '.jpg';
        Storage::disk('local')->put($path, $jpeg);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        return $path;
    }

    /** A separate copy of a stored photo (so a card keeps its photo if the employee's changes). */
    public function copyPhoto(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $copy = 'employees/' . Str::random(32) . '.jpg';
        Storage::disk('local')->copy($path, $copy);

        return $copy;
    }

    public function fileDataUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get($path));
    }

    /**
     * The authorised signature. Whatever was uploaded (photo of a signature
     * on paper, or a PNG) is turned into ink on a clear background, twice:
     * dark ink for letters, white ink for the ID card's dark band.
     */
    public function storeSignature(UploadedFile|string $source): string
    {
        $image = @imagecreatefromstring($source instanceof UploadedFile ? $source->get() : $this->fromDataUrl($source));
        if (! $image) {
            throw ValidationException::withMessages(['signature_file' => 'That signature image could not be read.']);
        }
        $base = 'hr/signature-' . Str::random(16);
        foreach (['dark' => [15, 23, 42], 'white' => [255, 255, 255]] as $tone => $rgb) {
            Storage::disk('local')->put("{$base}-{$tone}.png", $this->ink($image, $rgb));
        }

        return $base;
    }

    /** The signature as a data: URL ("dark" or "white" ink), so it prints and lands in PDFs as is. */
    public function signatureDataUrl(string $tone = 'dark'): ?string
    {
        $base = $this->settings()['signature'];
        $path = $base ? "{$base}-{$tone}.png" : null;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get($path));
    }

    public function deleteSignature(?string $base): void
    {
        if ($base) {
            Storage::disk('local')->delete(["{$base}-dark.png", "{$base}-white.png"]);
        }
    }

    /** Dark pixels become ink of the given colour; paper becomes clear. Trimmed to the ink. */
    protected function ink(\GdImage $src, array $rgb): string
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 900 / max($w, 1));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $img = imagecreatetruecolor($tw, $th);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        [$minX, $minY, $maxX, $maxY] = [$tw, $th, 0, 0];
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                $lum = 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
                $ink = (1 - $a / 127) * max(0, min(1, (225 - $lum) / 150));
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $rgb[0], $rgb[1], $rgb[2], 127 - (int) round($ink * 127)));
                if ($ink > 0.15) {
                    [$minX, $minY, $maxX, $maxY] = [min($minX, $x), min($minY, $y), max($maxX, $x), max($maxY, $y)];
                }
            }
        }
        if ($maxX > $minX && $maxY > $minY) {
            $out = imagecrop($out, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]) ?: $out;
            imagesavealpha($out, true);
        }

        ob_start();
        imagepng($out);

        return ob_get_clean();
    }

    public function photoDataUrl(Employee $employee): ?string
    {
        return $this->fileDataUrl($employee->photo);
    }

    protected function fromDataUrl(string $url): string
    {
        if (! preg_match('#^data:image/(png|jpe?g|webp);base64,#', $url, $m)) {
            throw ValidationException::withMessages(['photo' => 'That photo could not be read — use a JPG or PNG.']);
        }
        $bytes = base64_decode(substr($url, strlen($m[0])), true);
        if ($bytes === false || strlen($bytes) > 8 * 1024 * 1024) {
            throw ValidationException::withMessages(['photo' => 'That photo is too large (8 MB at most).']);
        }

        return $bytes;
    }
}
