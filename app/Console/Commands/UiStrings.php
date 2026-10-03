<?php

namespace App\Console\Commands;

use App\Support\BladeTextWrapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Lists every interface text the English / বাংলা dictionaries should
 * cover (templates' text and attributes, component titles, module, role
 * and menu labels) and reports what lang/ui/bn.json and en.json still lack.
 * --write saves the missing ones to lang/ui/missing-{bn,en}.json.
 */
class UiStrings extends Command
{
    protected $signature = 'app:ui-strings {--write : Save the missing keys}';

    protected $description = 'Find interface texts missing from the English / Bangla dictionaries';

    public function handle(): int
    {
        $keys = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php') || ! BladeTextWrapper::appliesTo($path)) {
                continue;
            }
            $w = new BladeTextWrapper(true);
            $w->wrap(file_get_contents($path));
            $keys += $w->keys;
        }

        $add = function ($text) use (&$keys) {
            $k = \App\Support\Ui::key((string) $text);
            if ($k !== '' && preg_match('/\p{L}/u', $k)) {
                $keys[$k] = true;
            }
        };
        foreach (config('modules') as $m) {
            $add($m['label']);
            foreach ($m['submodules'] ?? [] as $l) {
                $add($l);
            }
        }
        foreach (config('roles') as $r) {
            $add($r['label']);
            $add($r['description']);
        }
        $menu = config('adminlte.menu');
        array_walk_recursive($menu, function ($v, $k) use ($add) {
            if (in_array($k, ['text', 'header'], true) && $v !== 'lang_switch') {
                $add($v);
            }
        });
        // Labels kept in PHP and printed through …Label echoes
        $constants = [
            [\App\Models\SwitchEvent::class, 'TYPES'], [\App\Models\ActivityLog::class, 'ACTIONS'],
            [\App\Services\IpInventory::class, 'SOURCES'], [\App\Services\IpInventory::class, 'SCOPES'], [\App\Services\IpInventory::class, 'ROLES'],
            [\App\Services\VlanInventory::class, 'SOURCES'], [\App\Models\Customer::class, 'TYPES'], [\App\Models\PartnerEntry::class, 'TYPES'],
            [\App\Models\Transaction::class, 'ACCOUNTS'], [\App\Models\ProfitSheet::class, 'SECTIONS'], [\App\Models\TransactionCategory::class, 'PL_GROUPS'],
            [\App\Models\ZoneSettlement::class, 'CYCLES'], [\App\Models\Ticket::class, 'CATEGORIES'], [\App\Models\Ticket::class, 'PRIORITIES'],
            [\App\Models\Ticket::class, 'STATUSES'], [\App\Models\Ticket::class, 'SOURCES'], [\App\Models\Task::class, 'STATUSES'],
            [\App\Models\InventoryMovement::class, 'TYPES'], [\App\Models\InventoryItem::class, 'KINDS'],
            [\App\Models\HrLetter::class, 'TYPES'], [\App\Models\HrLetter::class, 'REASONS'], [\App\Services\HrDocs::class, 'THEMES'],
        ];
        foreach ($constants as [$class, $name]) {
            if (defined("{$class}::{$name}")) {
                $list = constant("{$class}::{$name}");
                array_walk_recursive($list, function ($v, $k) use ($add) {
                    if (is_string($v) && ! in_array($k, ['start', 'line', 1], true) && ! preg_match('/^(fas|far|fab) |^#[0-9a-f]{3,8}$/i', $v) && ! str_starts_with($v, 'badge') && ! in_array($v, ['success', 'danger', 'warning', 'info', 'secondary', 'primary', 'dark'], true)) {
                        $add($v);
                    }
                });
            }
        }
        foreach (['Brand not set', 'Core'] as $extra) {
            $add($extra);
        }

        // Texts handed to Ui::t() in templates and code: Ui::t('…'), Ui::t($x ? '…' : '…')
        $files = array_merge(File::allFiles(resource_path('views')), File::allFiles(app_path()));
        foreach ($files as $file) {
            if (in_array($file->getFilename(), ['BladeTextWrapper.php', 'UiStrings.php', 'Ui.php'], true)) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            $offset = 0;
            while (($pos = strpos($src, 'Ui::t(', $offset)) !== false) {
                $offset = $pos + 6;
                $depth = 1;
                $arg = '';
                for ($i = $offset, $n = strlen($src); $i < $n && $depth > 0; $i++) {
                    $c = $src[$i];
                    if ($c === "'" || $c === '"') {
                        $end = $i + 1;
                        while ($end < $n && $src[$end] !== $c) {
                            $end += $src[$end] === '\\' ? 2 : 1;
                        }
                        // A text, unless it's an array key ($row['name']) or compared (=== 'sale').
                        $before = rtrim($arg);
                        $after = ltrim(substr($src, $end + 1, 3));
                        if (! str_ends_with($before, '[') && ! str_starts_with($after, ']') && ! preg_match('/[=!]=$/', $before)) {
                            $add(stripcslashes(substr($src, $i + 1, $end - $i - 1)));
                        }
                        $arg .= '""';
                        $i = $end;
                        continue;
                    }
                    $depth += $c === '(' ? 1 : ($c === ')' ? -1 : 0);
                    $arg .= $c;
                }
            }
        }

        // Tab labels of the module headers
        foreach (File::glob(resource_path('views/components/*/header.blade.php')) as $f) {
            preg_match_all("/\\['[\\w.]+', (?:'[^']*'|\\[[^\\]]*\\]), '([^']+)'/", file_get_contents($f), $m);
            foreach ($m[1] as $l) {
                $add($l);
            }
        }

        $keys = array_keys($keys);
        sort($keys);
        $bangla = array_values(array_filter($keys, fn ($k) => preg_match('/[\x{0980}-\x{09FF}]/u', $k)));
        $english = array_values(array_diff($keys, $bangla));

        $bn = json_decode(@file_get_contents(lang_path('ui/bn.json')) ?: '{}', true) ?: [];
        $en = json_decode(@file_get_contents(lang_path('ui/en.json')) ?: '{}', true) ?: [];
        $missingBn = array_values(array_filter($english, fn ($k) => ! isset($bn[$k])));
        $missingEn = array_values(array_filter($bangla, fn ($k) => ! isset($en[$k])));

        $this->info(count($keys) . ' texts: ' . count($english) . ' English, ' . count($bangla) . ' Bangla');
        $this->line('Missing Bangla translations: ' . count($missingBn) . ' · missing English: ' . count($missingEn));

        if ($this->option('write')) {
            File::ensureDirectoryExists(lang_path('ui'));
            File::put(lang_path('ui/missing-bn.json'), json_encode($missingBn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            File::put(lang_path('ui/missing-en.json'), json_encode($missingEn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line('Saved lang/ui/missing-bn.json and missing-en.json');
        }

        return self::SUCCESS;
    }
}
