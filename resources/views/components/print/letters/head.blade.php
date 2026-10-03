{{-- Ref / date and the addressee, shared by the HR letters. --}}
<div class="ltr-top">
    <div><b>Ref:</b> {{ $letter->ref_no }}</div>
    <div><b>Date:</b> {{ $letter->letter_date->format('d F Y') }}</div>
</div>
<div class="ltr-to">
    To<br>
    <b>{{ $d['name'] }}</b><br>
    @if ($withRole ?? false){{ $d['designation'] }}@if (! empty($d['emp_id'])) · Emp ID: {{ $d['emp_id'] }}@endif<br>@endif
    @if (! empty($d['address'])){!! nl2br(e($d['address'])) !!}<br>@endif
    @if (! empty($d['phone']))Phone: {{ $d['phone'] }}@endif
</div>
