{{-- Termination letter body (English, printed on the pad). The reason sets the opening. --}}
@php
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d F Y') : null;
    $last = $date($d['effective_date']);
    $role = $d['designation'];
    $reason = $d['reason'] ?? 'other';
    $resignation = $reason === 'resignation';
    $opening = match ($reason) {
        'resignation' => 'With reference to your resignation letter' . (! empty($d['resignation_date']) ? ' dated ' . $date($d['resignation_date']) : '') . ', the management has accepted your resignation. You will be released from your duties as ' . $role . ' at the close of business on ' . $last . '.',
        'contract_end' => 'This is to inform you that your contract of employment as ' . $role . ' comes to an end, and your employment with Sunlit Network DC will end on ' . $last . '.',
        'redundancy' => 'Due to restructuring of the company\'s operations, the position of ' . $role . ' is no longer required. We regret to inform you that your employment will end on ' . $last . '.',
        'performance' => 'Despite earlier discussions and opportunities to improve, your performance has not met the required standard. The management has therefore decided to terminate your employment as ' . $role . ' with effect from ' . $last . '.',
        'absence' => 'You have been absent from duty without permission and have not resumed work. The management has therefore decided to terminate your employment as ' . $role . ' with effect from ' . $last . '.',
        'misconduct' => 'Following a review of the matter described below, the management has found you responsible for misconduct and has decided to terminate your employment as ' . $role . ' with effect from ' . $last . '.',
        'probation' => 'Your probation period has been reviewed and the management has decided not to confirm your appointment. Your employment as ' . $role . ' will therefore end on ' . $last . '.',
        default => 'The management has decided to terminate your employment as ' . $role . ' with effect from ' . $last . '.',
    };
    $thanks = ! in_array($reason, ['misconduct', 'absence'], true);
@endphp
<div class="ltr">
    @include('components.print.letters.head', ['withRole' => true])

    <p class="ltr-subject">Subject: <span>{{ $resignation ? 'Acceptance of Resignation and Release' : 'Termination of Employment' }}</span></p>

    <p>Dear {{ $d['name'] }},</p>
    <p>{{ $opening }}</p>
    @if (! empty($d['reason_details']))
        <p>{!! nl2br(e($d['reason_details'])) !!}</p>
    @endif
    @if (! empty($d['notice_pay']))
        <p>You will be paid salary in lieu of the notice period.</p>
    @endif

    <ol>
        @if (! empty($d['settlement']))<li><b>Final settlement:</b> {{ $d['settlement'] }}</li>@endif
        @if (! empty($d['property']))<li><b>Company property:</b> Please return the following to the office on or before your last working day: {{ $d['property'] }}</li>@endif
        <li><b>Confidentiality:</b> Your duty to keep company, customer and network information confidential continues after your employment ends.</li>
        @if (! empty($d['joining_date']))<li><b>Service:</b> Your service with the company is counted from {{ $date($d['joining_date']) }} to {{ $last }}.</li>@endif
    </ol>

    @if ($thanks)
        <p>We thank you for your service with Sunlit Network DC and wish you every success in the future.</p>
    @endif

    @include('components.print.letters.sign')

    <div class="ltr-accept">
        <b>Received:</b> I have received this letter.
        <div class="row">
            <div>Signature</div>
            <div>{{ $d['name'] }}</div>
            <div>Date</div>
        </div>
    </div>
</div>
