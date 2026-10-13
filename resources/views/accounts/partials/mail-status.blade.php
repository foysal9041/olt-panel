{{--
    Whether an invoice / receipt was emailed: $mail is its latest SentMail.
    block = true gives a one-line note; otherwise just an icon with the
    details on hover.
--}}
@php
    [$icon, $color] = match ($mail->status) {
        'sent' => ['fas fa-check-circle', 'text-success'],
        'failed' => ['fas fa-exclamation-circle', 'text-danger'],
        default => ['fas fa-clock', 'text-warning'],
    };
@endphp
@if ($block ?? false)
    <div @class(['alert small py-2 mb-3', 'alert-light border' => ! $mail->failed(), 'alert-danger' => $mail->failed()])>
        <i class="fas fa-envelope {{ $color }}"></i>
        @if ($mail->sent())
            Emailed to <strong>{{ $mail->to }}</strong> · {{ $mail->updated_at->format('d M Y, h:i A') }}{{ $mail->user ? ' · by ' . $mail->user->name : '' }}
        @elseif ($mail->failed())
            Last email{{ $mail->to ? ' to ' . $mail->to : '' }} failed ({{ $mail->updated_at->format('d M, h:i A') }}): {{ $mail->error }}
        @else
            Being emailed to <strong>{{ $mail->to }}</strong>…
        @endif
    </div>
@else
    <span class="{{ $color }} small ml-1" title="{{ $mail->summary() }}" data-toggle="tooltip"><i class="{{ $icon }}"></i></span>
@endif
