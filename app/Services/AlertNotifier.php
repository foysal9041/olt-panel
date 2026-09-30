<?php

namespace App\Services;

use App\Models\NocAlertSetting;

/**
 * One place every NOC alert goes through: sends it to each channel that's
 * switched on in Settings (Telegram, WhatsApp).
 */
class AlertNotifier
{
    public function __construct(
        protected TelegramNotifier $telegram,
        protected WhatsAppNotifier $whatsapp,
    ) {
    }

    /**
     * @param  string  $html  Telegram-style HTML (<b>, <code>, emoji)
     * @return array{sent: list<string>, errors: list<string>}  channels that delivered, and failures
     */
    public function send(string $html, ?NocAlertSetting $settings = null): array
    {
        $settings ??= NocAlertSetting::current();
        $sent = [];
        $errors = [];

        if ($settings->telegram_enabled) {
            $failed = $this->telegram->send($html, $settings);
            $failed ? array_push($errors, ...array_map(fn ($e) => "Telegram — {$e}", $failed)) : $sent[] = 'telegram';
        }

        if ($settings->whatsapp_enabled) {
            $failed = $this->whatsapp->send($html, $settings);
            $failed ? array_push($errors, ...array_map(fn ($e) => "WhatsApp — {$e}", $failed)) : $sent[] = 'whatsapp';
        }

        return ['sent' => $sent, 'errors' => $errors];
    }
}
