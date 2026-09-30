<?php

namespace App\Http\Controllers;

use App\Models\NocAlertSetting;
use App\Services\TelegramNotifier;
use App\Services\WhatsAppNotifier;
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
            'rx_warn_10g' => 'nullable|numeric|min:-40|max:5',
            'rx_warn_1g' => 'nullable|numeric|min:-40|max:5',
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
        $validated['alert_nttn_status'] = $request->boolean('alert_nttn_status');

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

    // ---- WhatsApp (Meta WhatsApp Business Cloud API) ----------------------

    public function editWhatsapp()
    {
        return view('settings.whatsapp', ['settings' => NocAlertSetting::current()]);
    }

    public function updateWhatsapp(Request $request)
    {
        $settings = NocAlertSetting::current();

        $validated = $request->validate([
            'whatsapp_phone_number_id' => ['nullable', 'regex:/^\d{6,25}$/'],
            'whatsapp_token' => ['nullable', 'string', 'min:20', 'max:1024', 'regex:/^[A-Za-z0-9_\-.|]+$/'],
            'whatsapp_recipients' => ['nullable', 'string', 'max:500', 'regex:/^[\d\s+,;\-()]*$/'],
            'whatsapp_mode' => 'required|in:template,text',
            'whatsapp_template' => ['nullable', 'required_if:whatsapp_mode,template', 'regex:/^[a-z0-9_]{1,100}$/'],
            'whatsapp_template_lang' => ['required', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'whatsapp_api_version' => ['required', 'regex:/^v\d{2}\.\d$/'],
        ], [
            'whatsapp_phone_number_id.regex' => 'The Phone Number ID is a long number from Meta → WhatsApp → API Setup (not the phone number itself).',
            'whatsapp_token.regex' => 'That does not look like a Meta access token.',
            'whatsapp_recipients.regex' => 'Recipients should be phone numbers with country code, e.g. 8801711000000, separated by commas.',
            'whatsapp_template.regex' => 'Template names are lowercase letters, numbers and underscores, e.g. noc_alert.',
            'whatsapp_template.required_if' => 'Enter the approved template name (or switch to Plain text mode).',
            'whatsapp_template_lang.regex' => 'Language code like en, en_US or bn.',
            'whatsapp_api_version.regex' => 'API version looks like v23.0.',
        ]);

        // Blank keeps the stored token so it never has to be shown again.
        if (blank($validated['whatsapp_token'] ?? null)) {
            unset($validated['whatsapp_token']);
        }

        // Tidy numbers: "+880 1711-000000" -> "8801711000000".
        $validated['whatsapp_recipients'] = implode(', ', (new NocAlertSetting(['whatsapp_recipients' => $validated['whatsapp_recipients'] ?? '']))->whatsappRecipients());
        $validated['whatsapp_enabled'] = $request->boolean('whatsapp_enabled');

        $settings->update($validated);

        if ($settings->whatsapp_enabled && ! $settings->whatsappReady()) {
            return back()->with('error', 'Saved, but WhatsApp alerts need a Phone Number ID, an access token, at least one recipient and (in Template mode) a template name.');
        }

        if (! $settings->whatsapp_enabled && $settings->whatsappReady()) {
            return back()->with('error', 'Saved, but WhatsApp alerts are OFF — turn on "Send alerts to WhatsApp" to receive messages.');
        }

        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsapp(WhatsAppNotifier $whatsapp)
    {
        $errors = $whatsapp->send(
            "✅ <b>Test alert</b>\nWhatsApp alerts from " . e(config('app.name')) . " are working.\n🕒 " . now()->format('d M Y, h:i:s A'),
            force: true
        );

        return $errors
            ? back()->with('error', 'Test message failed: ' . implode(' | ', $errors))
            : back()->with('success', 'Test message sent. Check WhatsApp on the recipient phones.');
    }
}
