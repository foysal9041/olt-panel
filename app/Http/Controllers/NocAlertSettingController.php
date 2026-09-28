<?php

namespace App\Http\Controllers;

use App\Models\NocAlertSetting;
use App\Services\TelegramNotifier;
use Illuminate\Http\Request;

class NocAlertSettingController extends Controller
{
    public function edit()
    {
        return view('settings.telegram', ['settings' => NocAlertSetting::current()]);
    }

    public function update(Request $request)
    {
        $settings = NocAlertSetting::current();

        $validated = $request->validate([
            'telegram_bot_token' => ['nullable', 'string', 'max:255', 'regex:/^\d+:[\w-]+$/'],
            'telegram_chat_ids' => ['nullable', 'string', 'max:255', 'regex:/^[\s,@\w-]*$/'],
            'rx_low_threshold' => 'nullable|numeric|min:-40|max:5',
        ], [
            'telegram_bot_token.regex' => 'That does not look like a bot token (it should look like 123456789:ABCdef...).',
            'telegram_chat_ids.regex' => 'Chat IDs should be numbers like -1001234567890 or @channelname, separated by commas.',
        ]);

        // Blank keeps the stored token so it never has to be shown again.
        if (blank($validated['telegram_bot_token'] ?? null)) {
            unset($validated['telegram_bot_token']);
        }

        $validated['telegram_enabled'] = $request->boolean('telegram_enabled');
        $validated['alert_port_status'] = $request->boolean('alert_port_status');
        $validated['alert_switch_status'] = $request->boolean('alert_switch_status');

        $settings->update($validated);

        if ($settings->telegram_enabled && (! $settings->telegram_bot_token || ! $settings->chatIds())) {
            return back()->with('error', 'Saved, but Telegram alerts need both a bot token and at least one chat ID.');
        }

        if (! $settings->telegram_enabled && $settings->telegram_bot_token && $settings->chatIds()) {
            return back()->with('error', 'Saved, but alerts are OFF — turn on "Send alerts to Telegram" to receive messages.');
        }

        return back()->with('success', 'Alert settings saved.');
    }

    public function test(TelegramNotifier $telegram)
    {
        $errors = $telegram->send(
            "✅ <b>Test alert</b>\nTelegram alerts from " . e(config('app.name')) . " are working.\n🕒 " . now()->format('d M Y, h:i:s A'),
            force: true
        );

        return $errors
            ? back()->with('error', 'Test message failed: ' . implode(' | ', $errors))
            : back()->with('success', 'Test message sent. Check your Telegram.');
    }
}
