<?php

namespace App\Support;

/**
 * Makes every piece of plain text in the app's Blade templates
 * translatable, without editing the templates: before a template is
 * compiled, each run of visible text between tags — and the placeholder,
 * title, aria-label and confirm-message attributes — is wrapped in
 * Ui::t(...), which looks it up in the dictionary for the user's language
 * (lang/ui/*.json) and otherwise leaves it as it was.
 *
 * Left untouched: Blade echoes, directives (and their arguments), @php,
 * @verbatim, comments, <script>/<style>/<textarea>/<pre>/<code>, the
 * styles/css/js/scripts sections, component tags' attributes, and text
 * without a letter in it.
 */
class BladeTextWrapper
{
    protected const RAW_TAGS = ['script', 'style', 'textarea', 'pre', 'code'];

    protected const RAW_SECTIONS = ['styles', 'css', 'js', 'scripts'];

    protected const ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'data-confirm-message'];

    /** Collected keys (extract mode) */
    public array $keys = [];

    protected string $src = '';

    protected int $n = 0;

    protected string $out = '';

    protected string $text = '';

    public function __construct(protected bool $collectOnly = false)
    {
    }

    public static function appliesTo(?string $path): bool
    {
        if (! $path) {
            return false;
        }
        $path = str_replace('\\', '/', $path);
        $views = str_replace('\\', '/', resource_path('views')) . '/';

        if (! str_starts_with($path, $views)) {
            return false;
        }
        if (str_starts_with($path, $views . 'vendor/') && ! str_starts_with($path, $views . 'vendor/adminlte/partials/footer/')) {
            return false;
        }

        // Printed papers handed to customers and partners (invoices, money
        // receipts, statements) keep the office's English wording.
        $rel = substr($path, strlen($views));

        return ! preg_match('#(^|/)(print[\w-]*|invoice|receipt|ledger-print)\.blade\.php$|^components/print/|^layouts/accounts-print|^accounts/partials/(print-styles|pdf-download)#', $rel);
    }

    /**
     * Echoes that print a label rather than data: $label, $somethingLabel(s)[…],
     * or a …Label() method — e.g. {{ $roleLabels[$e['role']] }}, {{ $e->typeLabel() }}.
     */
    public static function isLabelExpression(string $expr): bool
    {
        return (bool) preg_match('/^\$(label|\w*Labels?(\[.*\])?|\w*Label)$|^\$[\w>\-?]+->\w*Label\(\)$/', $expr);
    }

    public function wrap(string $src): string
    {
        $this->src = $src;
        $this->n = strlen($src);
        $this->out = '';
        $this->text = '';
        $i = 0;

        while ($i < $this->n) {
            $c = $src[$i];

            // {{-- comment --}}
            if ($c === '{' && $this->at($i, '{{--')) {
                $i = $this->copyUntil($i, '--}}');
                continue;
            }
            // {{ echo }}, {{{ }}}, {!! raw !!} — label-like echoes are translated too
            if ($c === '{' && ($this->at($i, '{{') || $this->at($i, '{!!'))) {
                if ($this->at($i, '{{') && ! $this->at($i, '{{{') && ($end = strpos($src, '}}', $i)) !== false) {
                    $expr = trim(substr($src, $i + 2, $end - $i - 2));
                    if (self::isLabelExpression($expr)) {
                        $this->flush();
                        $this->out .= $this->collectOnly ? substr($src, $i, $end + 2 - $i) : '{{ \\App\\Support\\Ui::t(' . $expr . ') }}';
                        $i = $end + 2;
                        continue;
                    }
                }
                $i = $this->copyUntil($i, $this->at($i, '{!!') ? '!!}' : '}}');
                continue;
            }
            if ($c === '@') {
                // @@ is a literal @; @{{ is an escaped echo — keep both as they are
                if ($this->at($i, '@@')) {
                    $this->text .= '@@';
                    $i += 2;
                    continue;
                }
                if ($this->at($i, '@{{')) {
                    $this->flush();
                    $i = $this->copyUntil($i, '}}');
                    continue;
                }
                $prev = $i > 0 ? $src[$i - 1] : ' ';
                if (! ctype_alnum($prev) && $prev !== '.' && $prev !== '_' && preg_match('/\G@([A-Za-z_][A-Za-z0-9_]*)/', $src, $m, 0, $i)) {
                    $i = $this->directive($i, $m[1]);
                    continue;
                }
            }
            if ($c === '<') {
                if ($this->at($i, '<!--')) {
                    $i = $this->copyUntil($i, '-->');
                    continue;
                }
                if ($this->at($i, '<!') && ! $this->at($i, '<!--')) {
                    $i = $this->copyUntil($i, '>');
                    continue;
                }
                if ($this->at($i, '<?')) {
                    $i = $this->copyUntil($i, '?>');
                    continue;
                }
                if (preg_match('/\G<(\/?)([A-Za-z][\w:.-]*)/', $src, $m, 0, $i)) {
                    $i = $this->tag($i, $m[1] === '/', strtolower($m[2]));
                    continue;
                }
            }

            $this->text .= $c;
            $i++;
        }

        $this->flush();

        return $this->out;
    }

    /** A Blade directive: copy it, its arguments and any raw body it has. */
    protected function directive(int $i, string $name): int
    {
        $this->flush();
        $lower = strtolower($name);
        $j = $i + 1 + strlen($name);

        // optional (arguments)
        $k = $j;
        while ($k < $this->n && ($this->src[$k] === ' ' || $this->src[$k] === "\t")) {
            $k++;
        }
        $hasArgs = $k < $this->n && $this->src[$k] === '(';
        $argsEnd = $hasArgs ? $this->matchParen($k) : $j - 1;

        if ($lower === 'php' && ! $hasArgs) {
            return $this->copyUntil($i, '@endphp');
        }
        if ($lower === 'verbatim') {
            return $this->copyUntil($i, '@endverbatim');
        }

        $call = substr($this->src, $i, $argsEnd + 1 - $i);
        // @section('title', 'Daily Cash Book') — the browser tab title
        if ($lower === 'section' && preg_match("/^@section\s*\(\s*'title'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)$/s", $call, $t)) {
            if ($this->collectOnly) {
                $this->collect(stripslashes($t[1]));
            } else {
                $call = "@section('title', \\App\\Support\\Ui::t('" . $t[1] . "'))";
            }
        }
        $this->out .= $call;
        $i = $argsEnd + 1;

        // @section('styles') … @endsection: raw CSS/JS, leave it alone
        if (($lower === 'section' || $lower === 'push') && $hasArgs) {
            $args = substr($this->src, $k + 1, $argsEnd - $k - 1);
            if (preg_match('/^\s*[\'"]([\w.-]+)[\'"]\s*$/', $args, $m) && in_array($m[1], self::RAW_SECTIONS, true)) {
                if (preg_match('/@(endsection|stop|endpush)\b/', $this->src, $e, PREG_OFFSET_CAPTURE, $i)) {
                    $this->out .= substr($this->src, $i, $e[0][1] - $i);

                    return $e[0][1];
                }
            }
        }

        return $i;
    }

    /** An HTML (or component) tag; translate chosen attributes; raw elements are copied whole. */
    protected function tag(int $i, bool $closing, string $name): int
    {
        $this->flush();
        $end = $this->tagEnd($i);
        $tag = substr($this->src, $i, $end - $i + 1);

        $isComponent = str_starts_with($name, 'x-') || str_starts_with($name, 'x:');
        if ($isComponent && $this->collectOnly && preg_match_all('/\s(?:title|subtitle)=(["\'])([^"\'{}@]*\p{L}[^"\'{}@]*)\1/u', $tag, $mm)) {
            foreach ($mm[2] as $v) {
                $this->collect(html_entity_decode($v));
            }
        }
        $this->out .= ($closing || $isComponent) ? $tag : $this->attributes($tag);
        $i = $end + 1;

        if (! $closing && in_array($name, self::RAW_TAGS, true) && ! str_ends_with(rtrim($tag), '/>')) {
            if (preg_match('/<\/' . preg_quote($name, '/') . '\s*>/i', $this->src, $m, PREG_OFFSET_CAPTURE, $i)) {
                $close = $m[0][1] + strlen($m[0][0]);
                $this->out .= substr($this->src, $i, $close - $i);

                return $close;
            }
        }

        return $i;
    }

    /** Index of the ">" closing the tag at $i (quotes, echoes and parentheses respected). */
    protected function tagEnd(int $i): int
    {
        $quote = null;
        $depth = 0;
        for ($j = $i + 1; $j < $this->n; $j++) {
            $c = $this->src[$j];
            if ($c === '{' && ($this->at($j, '{{') || $this->at($j, '{!!'))) {
                $close = strpos($this->src, $this->at($j, '{!!') ? '!!}' : '}}', $j);
                $j = $close === false ? $this->n : $close + 1;
                continue;
            }
            if ($quote) {
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($c === '>' && $depth === 0) {
                return $j;
            }
        }

        return $this->n - 1;
    }

    protected function attributes(string $tag): string
    {
        $names = implode('|', array_map('preg_quote', self::ATTRIBUTES));

        return preg_replace_callback('/(\s(?:' . $names . ')=)(["\'])([^"\'{}@]*?\p{L}[^"\'{}@]*)\2/u', function ($m) {
            $value = $m[3];
            if (! preg_match('/\p{L}/u', html_entity_decode($value, ENT_QUOTES | ENT_HTML5))) {
                return $m[0];
            }
            if ($this->collectOnly) {
                $this->collect($value);

                return $m[0];
            }

            // Raw: the value is already HTML (it may hold &amp; etc.), like the dictionary's.
            return $m[1] . $m[2] . '{!! \\App\\Support\\Ui::t(' . $this->php($value) . ') !!}' . $m[2];
        }, $tag);
    }

    /** Wrap the pending text run, keeping its surrounding whitespace. */
    protected function flush(): void
    {
        $t = $this->text;
        $this->text = '';
        if ($t === '') {
            return;
        }
        if (! preg_match('/\p{L}/u', html_entity_decode($t, ENT_QUOTES | ENT_HTML5))) {
            $this->out .= $t;

            return;
        }

        preg_match('/^(\s*)(.*?)(\s*)$/s', $t, $m);
        // Inside the string Blade no longer unescapes @@, so do it here.
        $m[2] = str_replace('@@', '@', $m[2]);
        if ($this->collectOnly) {
            $this->collect($m[2]);
            $this->out .= $t;

            return;
        }
        $this->out .= $m[1] . '{!! \\App\\Support\\Ui::t(' . $this->php($m[2]) . ') !!}' . $m[3];
    }

    protected function collect(string $text): void
    {
        $key = Ui::key($text);
        if ($key !== '') {
            $this->keys[$key] = true;
        }
    }

    protected function php(string $s): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
    }

    protected function at(int $i, string $needle): bool
    {
        return substr_compare($this->src, $needle, $i, strlen($needle)) === 0;
    }

    /** Copy from $i through the end of $needle (or to the end) and return the next index. */
    protected function copyUntil(int $i, string $needle): int
    {
        $this->flush();
        $end = strpos($this->src, $needle, $i + 1);
        $end = $end === false ? $this->n : $end + strlen($needle);
        $this->out .= substr($this->src, $i, $end - $i);

        return $end;
    }

    /** Index of the ")" matching the "(" at $i, quotes respected. */
    protected function matchParen(int $i): int
    {
        $depth = 0;
        $quote = null;
        for ($j = $i; $j < $this->n; $j++) {
            $c = $this->src[$j];
            if ($quote) {
                if ($c === '\\') {
                    $j++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return $this->n - 1;
    }
}
