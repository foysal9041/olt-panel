<?php

namespace App\Services;

use App\Support\Branding;

/**
 * The company's logo files: the uploaded ones (storage/app/branding), or
 * plain ones drawn from the company name and colour when none is uploaded.
 * Drawn files are cached under storage/app/branding/generated and redrawn
 * when the name, colour or source file changes.
 */
class BrandAssets
{
    public const WHICH = ['logo', 'icon', 'icon-gray', 'favicon'];

    protected const VERSION = 1;

    public function path(string $which): string
    {
        return match ($which) {
            'logo' => Branding::uploaded('logo') ?? $this->drawn('logo'),
            'icon' => Branding::uploaded('icon') ?? $this->drawn('icon'),
            'icon-gray' => $this->gray($this->path('icon')),
            'favicon' => Branding::uploaded('favicon') ?? $this->path('icon'),
            default => throw new \InvalidArgumentException("Unknown brand asset: $which"),
        };
    }

    protected function dir(): string
    {
        $dir = storage_path('app/branding/generated');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** A grey copy of the mark, for the letterhead's watermark. */
    protected function gray(string $source): string
    {
        $file = $this->dir() . '/icon-gray-' . substr(md5($source . '|' . @filemtime($source) . '|' . self::VERSION), 0, 12) . '.png';
        if (! is_file($file)) {
            $im = $this->load($source);
            imagefilter($im, IMG_FILTER_GRAYSCALE);
            $this->savePng($im, $file);
        }

        return $file;
    }

    /** The mark (initials on a rounded square) or the wordmark (mark + name). */
    protected function drawn(string $which): string
    {
        $name = Branding::name();
        $color = Branding::colorOrDesign('primary');
        $file = $this->dir() . "/$which-" . substr(md5($name . '|' . $color . '|' . self::VERSION), 0, 12) . '.png';
        if (is_file($file)) {
            return $file;
        }

        $s = 4; // drawn 4× and scaled down, for smooth edges
        $font = $this->font($name);
        $markSize = 200 * $s;
        $mark = $this->canvas($markSize, $markSize);
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $this->roundedRect($mark, 0, 0, $markSize - 1, $markSize - 1, (int) ($markSize * .22), imagecolorallocate($mark, $r, $g, $b));
        $initials = $this->initials($name);
        $this->centredText($mark, $initials, $font, $markSize * (mb_strlen($initials) > 1 ? .36 : .46), imagecolorallocate($mark, 255, 255, 255), 0, $markSize);

        if ($which === 'icon') {
            $this->savePng($this->shrink($mark, 200, 200), $file);

            return $file;
        }

        // Wordmark: the mark, then the name in dark ink (made for light backgrounds: documents, mail).
        $size = 92 * $s;
        $box = imagettfbbox($size, 0, $font, $name);
        $textW = abs($box[2] - $box[0]);
        $gap = 36 * $s;
        $w = min($markSize + $gap + $textW + 12 * $s, 1400 * $s);
        $im = $this->canvas($w, $markSize);
        imagecopy($im, $mark, 0, 0, 0, 0, $markSize, $markSize);
        $ink = imagecolorallocate($im, 15, 23, 42);
        $baseline = (int) ($markSize / 2 + ($size * .36));
        imagettftext($im, $size, 0, $markSize + $gap, $baseline, $ink, $font, $name);
        $this->savePng($this->shrink($im, (int) round($w / $s), 200), $file);

        return $file;
    }

    protected function initials(string $name): string
    {
        $words = preg_split('/[\s\-_.]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '•';
    }

    /** Carlito (Latin), or Hind Siliguri when the name is in Bangla. */
    protected function font(string $text): string
    {
        return resource_path(preg_match('/\p{Bengali}/u', $text) ? 'fonts/HindSiliguri-Bold.ttf' : 'fonts/Carlito-Bold.ttf');
    }

    protected function canvas(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);

        return $im;
    }

    protected function roundedRect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    protected function centredText(\GdImage $im, string $text, string $font, float $size, int $color, int $x, int $w): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        imagettftext($im, $size, 0, (int) ($x + ($w - $tw) / 2 - $box[0]), (int) ((imagesy($im) + $th) / 2 - $box[1]), $color, $font, $text);
    }

    protected function shrink(\GdImage $im, int $w, int $h): \GdImage
    {
        $out = $this->canvas($w, $h);
        imagealphablending($out, false);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));

        return $out;
    }

    protected function load(string $path): \GdImage
    {
        $im = @imagecreatefromstring((string) file_get_contents($path));
        if (! $im) {
            $im = $this->canvas(16, 16);
        }
        imagepalettetotruecolor($im);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        return $im;
    }

    protected function savePng(\GdImage $im, string $file): void
    {
        imagesavealpha($im, true);
        imagepng($im, $file . '.tmp');
        rename($file . '.tmp', $file);
    }
}
