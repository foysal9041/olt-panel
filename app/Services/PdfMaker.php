<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Turns a pdf/* Blade view into PDF bytes on the server (dompdf), for
 * attaching to emails. The pages use the same pad and wording as the
 * printed invoices and receipts; fonts come from resources/fonts and
 * images from public/images — nothing is fetched from the internet.
 */
class PdfMaker
{
    public function render(string $view, array $data = []): string
    {
        $fontCache = storage_path('fonts');
        if (! is_dir($fontCache)) {
            mkdir($fontCache, 0775, true);
        }

        $options = new Options([
            'chroot' => [resource_path('fonts'), public_path('images')],
            'fontDir' => $fontCache,
            'fontCache' => $fontCache,
            'tempDir' => sys_get_temp_dir(),
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultFont' => 'Carlito',
            'defaultPaperSize' => 'a4',
            'dpi' => 96,
        ]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }

    /** A safe file name, as the print pages' Download PDF names it: "INV-019 Navaron.pdf". */
    public static function fileName(string ...$parts): string
    {
        return trim(preg_replace('/[^\w\s.()-]+/u', ' ', str_replace('/', '-', implode(' ', $parts)))) . '.pdf';
    }
}
