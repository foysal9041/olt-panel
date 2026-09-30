<?php

namespace App\Services;

use App\Models\NocAlertSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends alerts through the WhatsApp Business Cloud API (Meta).
 *
 * Two modes (Settings → WhatsApp Alerts):
 *  - template (default): an approved message template with one body
 *    variable, e.g. "🚨 NOC alert: {{1}}". Works any time. WhatsApp doesn't
 *    allow line breaks inside a variable, so each alert is flattened to one
 *    line and long batches are split over several messages.
 *  - text: a normal message with line breaks. Only delivered if the
 *    recipient messaged the business number in the last 24 hours.
 *
 * Alerts are written for Telegram (HTML); they're converted here. The access
 * token only ever travels in the Authorization header — never in a URL or log.
 */
class WhatsAppNotifier
{
    /** Longest text we put in one template variable (WhatsApp caps the body at 1024). */
    protected const TEMPLATE_PARAM_MAX = 900;

    /** WhatsApp caps a text message at 4096 characters. */
    protected const TEXT_MAX = 4000;

    /** Meta error code => what to do about it. */
    protected const HINTS = [
        190 => 'the access token is invalid or has expired — create a permanent System User token',
        131030 => 'this number is not in the allowed list of your test phone number (add it in Meta → WhatsApp → API Setup)',
        131047 => 'more than 24 hours since this person last messaged you — use Template mode',
        131026 => 'the number is not on WhatsApp or cannot receive messages',
        132000 => 'the template needs exactly one {{1}} variable in its body',
        132001 => 'template not found — check the template name and language, and that it is approved',
        132018 => 'the alert text was rejected by the template (formatting)',
        100 => 'a setting is wrong (check the Phone Number ID and API version)',
        131056 => 'too many messages to this number in a short time — slowed down by WhatsApp',
    ];

    /**
     * Send an alert (Telegram-style HTML) to every recipient.
     * Returns an error string per failure (empty array = all sent).
     *
     * @return list<string>
     */
    public function send(string $html, ?NocAlertSetting $settings = null, bool $force = false): array
    {
        $settings ??= NocAlertSetting::current();

        if (! $settings->whatsapp_enabled && ! $force) {
            return ['WhatsApp alerts are turned off in Settings → WhatsApp Alerts.'];
        }

        if (! $settings->whatsappReady()) {
            Log::warning('WhatsApp alert not sent: settings are incomplete.');

            return ['WhatsApp is not fully set up (Phone Number ID, access token, recipients' . ($settings->whatsapp_mode === 'text' ? '' : ', template') . ').'];
        }

        $text = $this->toWhatsAppText($html);
        $messages = $settings->whatsapp_mode === 'text'
            ? $this->chunkText($text)
            : $this->templateParams($text);

        $errors = [];

        foreach ($settings->whatsappRecipients() as $to) {
            foreach ($messages as $message) {
                $error = $this->post($settings, $this->payload($settings, $to, $message));

                if ($error !== null) {
                    $errors[] = '+' . $to . ': ' . $error;
                    break;
                }
            }
        }

        foreach ($errors as $error) {
            Log::warning('WhatsApp alert failed: ' . $error);
        }

        return $errors;
    }

    protected function payload(NocAlertSetting $s, string $to, string $message): array
    {
        if ($s->whatsapp_mode === 'text') {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $message],
            ];
        }

        $template = [
            'name' => $s->whatsapp_template,
            'language' => ['code' => $s->whatsapp_template_lang ?: 'en'],
        ];

        // Meta's ready-made "hello_world" (for a first test) has no variable.
        if ($s->whatsapp_template !== 'hello_world') {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => [['type' => 'text', 'text' => $message]],
            ]];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ];
    }

    /**
     * POST one message; null when accepted, else a readable reason.
     */
    protected function post(NocAlertSetting $s, array $payload): ?string
    {
        $version = preg_match('/^v\d+\.\d+$/', (string) $s->whatsapp_api_version) ? $s->whatsapp_api_version : 'v23.0';
        $url = "https://graph.facebook.com/{$version}/" . rawurlencode((string) $s->whatsapp_phone_number_id) . '/messages';

        try {
            $response = Http::withToken((string) $s->whatsapp_token)->acceptJson()->timeout(15)->post($url, $payload);
        } catch (\Throwable $e) {
            return 'could not reach WhatsApp (' . str_replace((string) $s->whatsapp_token, '<token>', $e->getMessage()) . ')';
        }

        if ($response->successful()) {
            return null;
        }

        $code = (int) $response->json('error.code');
        $detail = $response->json('error.error_data.details') ?: $response->json('error.message') ?: 'HTTP ' . $response->status();
        $hint = self::HINTS[$code] ?? null;

        return trim(($hint ? ucfirst($hint) . '. ' : '') . "(WhatsApp {$code}: {$detail})");
    }

    /**
     * Telegram HTML -> WhatsApp formatting: <b> becomes *bold*, tags go.
     */
    public function toWhatsAppText(string $html): string
    {
        $text = preg_replace('#</?(b|strong)>#i', '*', $html);
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Template variables can't hold line breaks: one line per alert,
     * alerts packed into as few variables as fit.
     *
     * @return list<string>
     */
    public function templateParams(string $text): array
    {
        $alerts = array_filter(array_map(function ($block) {
            $line = implode(' · ', array_filter(array_map('trim', explode("\n", $block))));
            $line = preg_replace('/\s{2,}/', ' ', $line);

            return mb_strlen($line) > self::TEMPLATE_PARAM_MAX ? mb_substr($line, 0, self::TEMPLATE_PARAM_MAX - 1) . '…' : $line;
        }, preg_split("/\n\s*\n/", $text)));

        $params = [];
        $current = '';

        foreach ($alerts as $alert) {
            $joined = $current === '' ? $alert : $current . ' ‖ ' . $alert;

            if (mb_strlen($joined) > self::TEMPLATE_PARAM_MAX && $current !== '') {
                $params[] = $current;
                $current = $alert;
            } else {
                $current = $joined;
            }
        }

        return $current === '' ? $params : [...$params, $current];
    }

    /**
     * @return list<string>
     */
    protected function chunkText(string $text): array
    {
        if (mb_strlen($text) <= self::TEXT_MAX) {
            return [$text];
        }

        $chunks = [];
        $current = '';

        foreach (explode("\n", $text) as $line) {
            if (mb_strlen($current) + mb_strlen($line) + 1 > self::TEXT_MAX) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= ($current === '' ? '' : "\n") . $line;
        }

        return $current === '' ? $chunks : [...$chunks, $current];
    }
}
