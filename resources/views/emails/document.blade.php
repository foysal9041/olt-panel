@use('App\Support\Branding')
@php($brandColor = Branding::color('primary') ?? '#0d6fd8')
{{-- Body of App\Mail\DocumentMail: invoice / money receipt sent to a client, the PDF attached. Inline styles only — mail apps drop <style>. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }}</title></head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:Calibri, 'Segoe UI', Arial, sans-serif; color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0;">
            <tr>
                <td style="padding:20px 28px 16px; border-bottom:3px solid {{ $brandColor }};">
                    <img src="{{ $message->embed(Branding::path('logo')) }}" alt="{{ Branding::legalName() }}" height="48" style="display:block; height:48px; width:auto; border:0;">
                </td>
            </tr>
            <tr>
                <td style="padding:24px 28px 8px; font-size:15px; line-height:1.55;">
                    <p style="margin:0 0 12px;">{{ $greeting }}</p>
                    <p style="margin:0 0 18px;">{{ $intro }}</p>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                        @foreach ($rows as $label => $value)
                            <tr>
                                <td style="padding:8px 12px; border:1px solid #e2e8f0; background:#f8fafc; color:#475569; width:45%;">{{ $label }}</td>
                                <td style="padding:8px 12px; border:1px solid #e2e8f0; font-weight:bold; {{ $loop->last ? 'color:#0d5fc0;' : '' }}">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>

                    @if ($note)
                        <p style="margin:16px 0 0; padding:10px 12px; background:#fff7ed; border-left:3px solid #f59e0b; font-size:14px;">{{ $note }}</p>
                    @endif

                    <p style="margin:18px 0 0;">The PDF copy is attached to this email (<strong>{{ $fileName }}</strong>).</p>
                    @if (Branding::phone())
                        <p style="margin:12px 0 0;">For any question about this bill, please call <strong>{{ Branding::phone() }}</strong> or reply to this email.</p>
                    @else
                        <p style="margin:12px 0 0;">For any question about this bill, please reply to this email.</p>
                    @endif
                    <p style="margin:18px 0 0;">Regards,<br><strong>{{ Branding::legalName() }}</strong></p>
                </td>
            </tr>
            <tr>
                <td style="padding:16px 28px; background:{{ $brandColor }}; color:#ffffff; font-size:12px; line-height:1.5;">
                    {{ Branding::legalName() }}{{ Branding::address() ? ', ' . Branding::address() : '' }}<br>
                    {{ Branding::phone() }}{{ Branding::phone() && Branding::website() ? ' · ' : '' }}@if (Branding::website())<a href="{{ Branding::websiteUrl() }}" style="color:#ffffff;">{{ Branding::website() }}</a>@endif
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
