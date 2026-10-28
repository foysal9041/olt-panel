<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Support\Branding;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Set the company's branding from the command line (install.sh uses it):
 * the same settings as Settings → Company Branding.
 *
 *   php artisan app:branding --name="Acme Net" --legal-name="Acme Net Ltd" --primary-color=#e11d48 --logo=/tmp/logo.png
 *   php artisan app:branding --show
 */
class SetBranding extends Command
{
    protected $signature = 'app:branding
        {--name= : Short company name}
        {--legal-name= : Name on documents}
        {--app-title= : Browser title (default: "<name> ERP")}
        {--tagline= : Line under the name on the sign-in page}
        {--email=} {--accounts-email=} {--phone=} {--website=}
        {--address= : Address; separate printed lines with "|"}
        {--address-short= : Area for small letterheads}
        {--invoice-prefix= : Start of invoice numbers, e.g. INV/}
        {--copyright-holder=} {--footer-credit=} {--privacy-url=} {--terms-url=}
        {--primary-color=} {--secondary-color=} {--accent-color=} {--background-color= : Colours as #rrggbb, or "default"}
        {--logo= : Path of a wide logo (PNG/JPG/WebP)}
        {--icon= : Path of a square mark (PNG/JPG/WebP)}
        {--favicon= : Path of a favicon (PNG/ICO)}
        {--show : Print the current branding}';

    protected $description = 'Set the company name, contact details, colours and logos of this installation';

    public function handle()
    {
        $current = AppSetting::get('branding', config('branding.defaults'));

        $changed = [];
        foreach (array_keys(config('branding.defaults')) as $key) {
            $opt = str_replace('_', '-', $key);
            if (in_array($key, ['logo', 'icon', 'favicon'], true) || ! $this->input->hasOption($opt) || $this->option($opt) === null) {
                continue;
            }
            $value = trim((string) $this->option($opt));
            if (str_ends_with($key, '_color')) {
                if ($value === '' || strtolower($value) === 'default') {
                    $value = null;
                } elseif (! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    $this->error("--$opt must look like #1d4ed8 (or \"default\").");

                    return Command::FAILURE;
                }
            }
            if ($key === 'address') {
                $value = str_replace('|', "\n", $value);
            }
            if ($key === 'name' && $value === '') {
                $this->error('--name can\'t be empty.');

                return Command::FAILURE;
            }
            $current[$key] = $value === '' ? null : $value;
            $changed[] = $key;
        }

        foreach (['logo', 'icon', 'favicon'] as $which) {
            $path = $this->option($which);
            if ($path === null) {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $ok = is_file($path) && filesize($path) <= config('branding.upload_max_kb') * 1024
                && in_array($ext, explode(',', config('branding.upload_mimes')[$which]), true)
                && ($ext === 'ico' || @getimagesize($path));
            if (! $ok) {
                $this->error("--$which: not a readable " . str_replace(',', '/', config('branding.upload_mimes')[$which]) . ' image under ' . config('branding.upload_max_kb') . ' KB.');

                return Command::FAILURE;
            }
            $dir = storage_path('app/branding');
            is_dir($dir) || mkdir($dir, 0775, true);
            if (! empty($current[$which]) && is_file($old = $dir . '/' . basename($current[$which]))) {
                @unlink($old);
            }
            $name = $which . '-' . Str::lower(Str::random(10)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            copy($path, "$dir/$name");
            $current[$which] = $name;
            $changed[] = $which;
        }

        if ($changed) {
            AppSetting::put('branding', $current);
            Branding::flush();
            $this->info('Saved: ' . implode(', ', $changed));
        }
        if ($this->option('show') || ! $changed) {
            foreach (Branding::all() as $k => $v) {
                $this->line(str_pad($k, 18) . ' ' . str_replace("\n", ' | ', (string) $v));
            }
        }

        return Command::SUCCESS;
    }
}
