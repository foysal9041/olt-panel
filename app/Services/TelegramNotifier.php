<?php

namespace App\Services;

use App\Models\NocAlertSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    /**
     * Send an HTML-formatted message to every configured chat.
     * Returns an error string per failed chat (empty array = all sent).
     *
     * @return list<string>
     */
    public function send(string $html, ?NocAlertSetting $settings = null, bool $force = false): array
    {
        $settings ??= NocAlertSetting::current();

        if (! $settings->telegram_enabled && ! $force) {
            Log::info('Telegram alert not sent: alerts are turned off in Settings → Telegram.');

            return ['Telegram alerts are turned off in Settings → Telegram.'];
        }

        if (! $settings->telegram_bot_token) {
            Log::warning('Telegram alert not sent: no bot token saved in Settings → Telegram.');

            return ['No Telegram bot token saved.'];
        }

        $chatIds = $settings->chatIds();

        if (! $chatIds) {
            return ['No Telegram chat ID configured.'];
        }

        $errors = [];

        foreach ($chatIds as $chatId) {
            // Telegram caps a message at 4096 characters.
            foreach ($this->chunks($html) as $chunk) {
                try {
                    $response = Http::timeout(10)
                        ->asForm()
                        ->post("https://api.telegram.org/bot{$settings->telegram_bot_token}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => $chunk,
                            'parse_mode' => 'HTML',
                            'disable_web_page_preview' => 'true',
                        ]);

                    if (! $response->successful()) {
                        $errors[] = "Chat {$chatId}: " . ($response->json('description') ?? 'HTTP ' . $response->status());
                        break;
                    }
                } catch (\Throwable $e) {
                    // The request URL carries the bot token; never let it
                    // reach the log or the screen.
                    $errors[] = "Chat {$chatId}: " . str_replace($settings->telegram_bot_token, '<token>', $e->getMessage());
                    break;
                }
            }
        }

        foreach ($errors as $error) {
            Log::warning('Telegram alert failed: ' . $error);
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    protected function chunks(string $html): array
    {
        if (mb_strlen($html) <= 4000) {
            return [$html];
        }

        $chunks = [];
        $current = '';

        foreach (explode("\n", $html) as $line) {
            if (mb_strlen($current) + mb_strlen($line) + 1 > 4000) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= ($current === '' ? '' : "\n") . $line;
        }

        return $current === '' ? $chunks : [...$chunks, $current];
    }
}
