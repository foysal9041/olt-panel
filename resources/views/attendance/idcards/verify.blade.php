<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ID Card Check — Sunlit Network DC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ok: #16a34a; --bad: #dc2626; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: linear-gradient(160deg, #0a2a5e, #123f86 60%, #1e88e5); font-family: 'Poppins', system-ui, sans-serif; color: #0f172a; }
        .box { width: 100%; max-width: 380px; background: #fff; border-radius: 22px; overflow: hidden; box-shadow: 0 20px 50px -15px rgba(0, 0, 0, .45); text-align: center; }
        .top { padding: 22px 20px 14px; }
        .top img { width: 190px; max-width: 70%; }
        .status { padding: 12px; color: #fff; font-weight: 700; letter-spacing: .04em; }
        .status.ok { background: var(--ok); }
        .status.bad { background: var(--bad); }
        .status small { display: block; font-weight: 500; letter-spacing: 0; opacity: .9; }
        .who { padding: 22px 20px 26px; }
        .photo { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 0 0 3px #1e88e5; }
        .ph { display: inline-grid; place-items: center; width: 120px; height: 120px; border-radius: 50%; background: #e2e8f0; color: #64748b; font-size: 40px; font-weight: 700; }
        h1 { margin: 14px 0 2px; font-size: 21px; text-transform: uppercase; letter-spacing: .03em; }
        .role { color: #1e88e5; font-weight: 500; }
        dl { margin: 16px 0 0; padding: 0; text-align: left; font-size: 14px; }
        dl div { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed #e2e8f0; }
        dt { color: #64748b; } dd { margin: 0; font-weight: 600; }
        .foot { padding: 12px; font-size: 12px; color: #64748b; background: #f8fafc; }
    </style>
</head>
<body>
    @php
        $status = $card->exists ? $card->status() : (($card->employee && $card->employee->hasLeft()) ? 'left' : 'valid');
        $valid = $status === 'valid';
        $line = [
            'valid' => 'Card is valid' . ($card->expiry_date ? ' until ' . $card->expiry_date->format('d M Y') : ''),
            'expired' => 'This card expired on ' . $card->expiry_date?->format('d M Y'),
            'revoked' => 'This card has been cancelled',
            'left' => 'No longer works at Sunlit Network DC' . ($card->employee?->left_on ? ' (since ' . $card->employee->left_on->format('d M Y') . ')' : ''),
        ][$status];
    @endphp
    <div class="box">
        <div class="top"><img src="{{ asset('images/logo.png') }}" alt="Sunlit Network DC"></div>
        <div class="status {{ $valid ? 'ok' : 'bad' }}">
            {{ $valid ? '✔ VALID ID CARD' : '✖ NOT VALID' }}
            <small>{{ $line }}</small>
        </div>
        <div class="who">
            @if ($photo)
                <img src="{{ $photo }}" class="photo" alt="">
            @else
                <span class="ph">{{ collect(preg_split('/\s+/', trim($card->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
            @endif
            <h1>{{ $card->name }}</h1>
            <div class="role">{{ $card->designation }}</div>
            <dl>
                @if ($card->card_no)<div><dt>Card no</dt><dd>{{ $card->card_no }}</dd></div>@endif
                @if ($card->id_no)<div><dt>{{ $card->employee_id ? 'Emp ID' : 'ID No' }}</dt><dd>{{ $card->id_no }}</dd></div>@endif
                @if ($card->department)<div><dt>Department</dt><dd>{{ $card->department }}</dd></div>@endif
                @if ($card->exists)<div><dt>Issued</dt><dd>{{ $card->issue_date->format('d M Y') }}</dd></div>@endif
                @if ($card->expiry_date)<div><dt>Expires</dt><dd>{{ $card->expiry_date->format('d M Y') }}</dd></div>@endif
            </dl>
        </div>
        <div class="foot">Checked {{ now()->format('d M Y, h:i A') }} · {{ $settings['office_phone'] ?: $settings['phone'] }}</div>
    </div>
</body>
</html>
