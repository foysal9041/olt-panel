<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class NocAlertSetting extends Model
{
    use LogsActivity {
        loggableChanges as baseLoggableChanges;
    }

    /** Secrets: logged as "(changed)", never their value. */
    public const SECRETS = ['telegram_bot_token', 'whatsapp_token'];

    /**
     * Log that a token changed — but never the token itself.
     */
    protected function loggableChanges(): array
    {
        $changes = $this->baseLoggableChanges();

        foreach (self::SECRETS as $secret) {
            if ($this->wasChanged($secret)) {
                $changes[$secret] = ['old' => '•••', 'new' => '(changed)'];
            }
        }

        return $changes;
    }

    protected $fillable = [
        'telegram_enabled',
        'telegram_bot_token',
        'telegram_chat_ids',
        'alert_port_status',
        'alert_switch_status',
        'alert_nttn_status',
        'rx_low_threshold',
        'rx_warn_10g',
        'rx_warn_1g',
        'whatsapp_enabled',
        'whatsapp_phone_number_id',
        'whatsapp_token',
        'whatsapp_recipients',
        'whatsapp_mode',
        'whatsapp_template',
        'whatsapp_template_lang',
        'whatsapp_api_version',
    ];

    protected $hidden = ['telegram_bot_token', 'whatsapp_token'];

    protected function casts(): array
    {
        return [
            'telegram_enabled' => 'boolean',
            'telegram_bot_token' => 'encrypted',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_token' => 'encrypted',
            'alert_port_status' => 'boolean',
            'alert_switch_status' => 'boolean',
            'alert_nttn_status' => 'boolean',
            'rx_low_threshold' => 'float',
            'rx_warn_10g' => 'float',
            'rx_warn_1g' => 'float',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'telegram_enabled' => false,
            'alert_port_status' => true,
            'alert_switch_status' => true,
            'alert_nttn_status' => true,
            'rx_warn_10g' => -15,
            'rx_warn_1g' => -18,
        ]);
    }

    /**
     * @return list<string>
     */
    public function chatIds(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $this->telegram_chat_ids))));
    }

    /**
     * WhatsApp numbers with country code, digits only: "8801711000000".
     * A Bangladeshi local number (01711000000) gets its 88 prefix; a
     * leading 00 international prefix is dropped.
     *
     * @return list<string>
     */
    public function whatsappRecipients(): array
    {
        return array_values(array_unique(array_filter(array_map(function ($n) {
            $n = preg_replace('/\D/', '', $n);
            $n = preg_replace('/^00/', '', $n);

            return preg_match('/^01\d{9}$/', $n) ? '88' . $n : $n;
        }, preg_split('/[,;\n]+/', (string) $this->whatsapp_recipients)))));
    }

    public function whatsappReady(): bool
    {
        return $this->whatsapp_phone_number_id && $this->whatsapp_token && $this->whatsappRecipients()
            && ($this->whatsapp_mode === 'text' || $this->whatsapp_template);
    }

    /** At least one alert channel is switched on. */
    public function anyChannelEnabled(): bool
    {
        return $this->telegram_enabled || $this->whatsapp_enabled;
    }

    protected function activityLogLabel(): string
    {
        return 'Alert Settings';
    }

    protected function activityLogTitle(): string
    {
        return 'Telegram, WhatsApp & thresholds';
    }

    /**
     * Tokens are secrets: never put them (even encrypted) in the log.
     */
    protected function activityLogExcept(): array
    {
        return [...self::SECRETS, 'created_at', 'updated_at'];
    }
}
