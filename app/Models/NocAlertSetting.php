<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NocAlertSetting extends Model
{
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
    ];

    protected $hidden = ['telegram_bot_token'];

    protected function casts(): array
    {
        return [
            'telegram_enabled' => 'boolean',
            'telegram_bot_token' => 'encrypted',
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
}
