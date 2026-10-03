{{-- Appointment letter body (English, printed on the pad). --}}
@php
    $d = array_merge(['name' => '', 'designation' => '', 'department' => null, 'emp_id' => null, 'address' => null, 'phone' => null,
        'signatory' => '', 'signatory_title' => ''], $d ?? []);
    $d += ['joining_date' => null, 'salary_basic' => 0, 'salary_house_rent' => null, 'salary_other' => null, 'probation_months' => 0,
        'working_hours' => null, 'weekly_off' => null, 'reporting_to' => null, 'workplace' => null, 'notice_days' => 0, 'terms' => ''];
    $tk = fn ($v) => number_format((float) $v, 0);
    $gross = (float) ($d['salary_basic'] ?? 0) + (float) ($d['salary_house_rent'] ?? 0) + (float) ($d['salary_other'] ?? 0);
    $parts = array_filter([
        'Basic' => $d['salary_basic'] ?? null,
        'House Rent' => $d['salary_house_rent'] ?? null,
        'Other Allowance' => $d['salary_other'] ?? null,
    ], fn ($v) => (float) $v > 0);
    $terms = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $d['terms'] ?? ''))));
    $joining = $d['joining_date'] ? \Illuminate\Support\Carbon::parse($d['joining_date'])->format('d F Y') : '—';
    $probation = (int) ($d['probation_months'] ?? 0);
    $notice = (int) ($d['notice_days'] ?? 0);
    $many = count($terms) > 5;
@endphp
<div @class(['ltr', 'compact' => $many])>
    @include('components.print.letters.head')

    <p class="ltr-subject">Subject: <span>Letter of Appointment — {{ $d['designation'] }}</span></p>

    <p>Dear {{ $d['name'] }},</p>
    <p>
        We are pleased to appoint you as <b>{{ $d['designation'] }}</b>@if (! empty($d['department'])) in the <b>{{ $d['department'] }}</b> department{{ '' }}@endif
        of Sunlit Network DC. Your appointment is subject to the following terms and conditions:
    </p>

    <ol>
        <li><b>Joining:</b> You will join on <b>{{ $joining }}</b>@if (! empty($d['workplace'])) at {{ $d['workplace'] }}@endif.@if (! empty($d['emp_id'])) Your employee ID is {{ $d['emp_id'] }}.@endif</li>
        <li><b>Salary:</b> Your monthly gross salary will be <b>Tk {{ $tk($gross) }}</b> ({{ \App\Support\NumberWords::international($gross) }})@if (count($parts) > 1), made up of {{ collect($parts)->map(fn ($v, $k) => "{$k} Tk " . $tk($v))->implode(', ') }}@endif. Salary is paid monthly, as per company practice.</li>
        @if ($probation)
            <li><b>Probation:</b> You will be on probation for {{ $probation }} {{ $probation === 1 ? 'month' : 'months' }} from your joining date. On satisfactory performance, your appointment will be confirmed in writing.</li>
        @endif
        <li><b>Office hours:</b> {{ $d['working_hours'] ?: 'As per company schedule' }}@if (! empty($d['weekly_off'])), with {{ $d['weekly_off'] }} as the weekly holiday{{ '' }}@endif, or as per the duty roster.</li>
        <li><b>Duties:</b> @if (! empty($d['reporting_to']))You will report to {{ $d['reporting_to'] }} and @else You will @endif carry out the duties given to you from time to time.</li>
        @if ($notice)
            <li><b>Notice:</b> After confirmation, either side may end the employment by giving {{ $notice }} days' written notice, or salary in lieu of notice.@if ($probation) During probation, 7 days' notice applies.@endif</li>
        @endif
        @foreach ($terms as $term)
            <li>{{ $term }}</li>
        @endforeach
    </ol>

    <p>Please sign the duplicate copy of this letter to confirm that you accept this appointment. We welcome you to Sunlit Network DC and wish you a successful career with us.</p>

    @include('components.print.letters.sign')

    <div class="ltr-accept">
        <b>Acceptance:</b> I have read and understood the terms and conditions above and accept this appointment.
        <div class="row">
            <div>Signature</div>
            <div>{{ $d['name'] }}</div>
            <div>Date</div>
        </div>
    </div>
</div>
