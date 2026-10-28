<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Services\BrandAssets;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Settings → Company Branding: the company this installation belongs to —
 * names, contact details, logos, colours and document/footer text.
 */
class BrandingController extends Controller
{
    public const COLORS = ['primary', 'secondary', 'accent', 'background'];

    public function edit()
    {
        return view('settings.branding', [
            'b' => Branding::all(),
            'appUrl' => config('app.url'),
        ]);
    }

    public function update(Request $request)
    {
        $max = (int) config('branding.upload_max_kb', 2048);
        $mimes = config('branding.upload_mimes');
        $hex = 'nullable|regex:/^#[0-9a-fA-F]{6}$/';
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'legal_name' => 'nullable|string|max:150',
            'app_title' => 'nullable|string|max:100',
            'tagline' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'accounts_email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:60',
            'website' => ['nullable', 'string', 'max:150', 'regex:#^(https?://)?[a-z0-9.-]+\.[a-z]{2,}(/\S*)?$#i'],
            'address' => 'nullable|string|max:500',
            'address_short' => 'nullable|string|max:150',
            'invoice_prefix' => ['required', 'string', 'max:20', 'regex:#^[A-Za-z0-9/._-]+$#'],
            'copyright_holder' => 'nullable|string|max:150',
            'footer_credit' => 'nullable|string|max:200',
            'privacy_url' => 'nullable|url:http,https|max:255',
            'terms_url' => 'nullable|url:http,https|max:255',
            'primary_color' => $hex, 'secondary_color' => $hex, 'accent_color' => $hex, 'background_color' => $hex,
            'logo' => "nullable|file|image|mimes:{$mimes['logo']}|max:$max|dimensions:min_height=32,max_width=4000,max_height=4000",
            'icon' => "nullable|file|image|mimes:{$mimes['icon']}|max:$max|dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000",
            'favicon' => "nullable|file|mimes:{$mimes['favicon']}|max:512",
        ], [
            'website.regex' => 'Enter a website like www.example.com.',
            'invoice_prefix.regex' => 'Use letters, digits and / . _ - only (e.g. INV/ or ACME/DC/).',
        ]);

        $before = Branding::all();
        $new = [];
        foreach (array_keys(config('branding.defaults')) as $key) {
            if (in_array($key, Branding::FILES, true)) {
                continue;
            }
            $value = $data[$key] ?? null;
            $new[$key] = is_string($value) ? trim($value) : $value;
        }
        // A colour counts only when "Use my colour" is ticked; otherwise the design's own.
        foreach (self::COLORS as $c) {
            $new["{$c}_color"] = $request->boolean("custom_$c") && filled($data["{$c}_color"] ?? null) ? strtolower($data["{$c}_color"]) : null;
        }
        foreach (Branding::FILES as $which) {
            $new[$which] = $before[$which] ?? null;
            if ($request->boolean("remove_$which")) {
                $this->deleteFile($new[$which]);
                $new[$which] = null;
            }
            if ($request->hasFile($which)) {
                $this->deleteFile($new[$which]);
                $new[$which] = $this->store($request->file($which), $which);
            }
        }

        AppSetting::put('branding', $new);
        Branding::flush();

        $changed = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', array_intersect_key($before, $new))));
        if ($changed) {
            ActivityLog::record('updated', 'Updated company branding: ' . implode(', ', $changed));
        }

        return redirect()->route('settings.branding')->with('success', 'Company branding saved.');
    }

    /** An uploaded logo / icon / favicon, kept outside the code so upgrades keep it. */
    protected function store(UploadedFile $file, string $which): string
    {
        $dir = storage_path('app/branding');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = strtolower($file->getClientOriginalExtension()) === 'ico' ? 'ico' : ($file->guessExtension() ?: 'png');
        $name = $which . '-' . Str::lower(Str::random(10)) . '.' . $ext;
        $file->move($dir, $name);

        return $name;
    }

    protected function deleteFile(?string $name): void
    {
        if ($name && is_file($path = storage_path('app/branding/' . basename($name)))) {
            @unlink($path);
        }
    }

    /** The logo, mark, grey mark or favicon — public, for the sign-in page and e-mail too. */
    public function asset(string $which, BrandAssets $assets)
    {
        abort_unless(in_array($which, BrandAssets::WHICH, true), 404);
        $path = $assets->path($which);
        $type = str_ends_with($path, '.ico') ? 'image/x-icon' : (mime_content_type($path) ?: 'image/png');

        return response()->file($path, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
