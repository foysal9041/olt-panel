<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Models\SentMail;
use App\Rules\EmailList;
use App\Services\DocumentMailer;
use Illuminate\Http\Request;

/**
 * Settings → Email: the office mailbox (company domain SMTP) that invoices
 * and money receipts are emailed from, and a test mail to check it.
 */
class MailSettingController extends Controller
{
    public function edit()
    {
        return view('settings.mail', [
            'settings' => MailSetting::current(),
            'recent' => SentMail::with('user:id,name')->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = MailSetting::current();

        $validated = $request->validate([
            'host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => 'required|integer|between:1,65535',
            'encryption' => 'required|in:' . implode(',', array_keys(MailSetting::ENCRYPTIONS)),
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|email|max:255',
            'bcc' => ['nullable', 'string', 'max:500', new EmailList],
        ], [
            'host.regex' => 'The mail server is a host name like mail.sunlitnetwork.com — no http:// or slashes.',
        ]);

        // Blank keeps the saved password so it never has to be shown again.
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }
        $validated['bcc'] = implode(', ', \App\Models\Customer::parseEmails($validated['bcc'] ?? null)) ?: null;
        $validated['enabled'] = $request->boolean('enabled');

        $settings->update($validated);

        if ($settings->enabled && ! $settings->complete()) {
            return back()->with('error', 'Saved, but emailing needs the mail server, username, password and From address.');
        }

        return back()->with('success', $settings->enabled
            ? 'Email settings saved. Send a test mail to check them.'
            : 'Saved — emailing invoices is OFF until you turn it on.');
    }

    public function test(Request $request, DocumentMailer $mailer)
    {
        $data = $request->validate(['test_to' => 'required|email|max:255']);

        if (! MailSetting::current()->complete()) {
            return back()->withInput()->with('error', 'Fill in the mail server, username, password and From address first.');
        }

        $log = $mailer->test($data['test_to'], $request->user()->id);

        return $log->sent()
            ? back()->withInput()->with('success', "Test mail sent to {$data['test_to']} — check that inbox (and its spam folder).")
            : back()->withInput()->with('error', 'Test mail failed: ' . $log->error);
    }
}
