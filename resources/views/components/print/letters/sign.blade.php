{{-- The company's signature block. --}}
<div class="ltr-sign">
    Sincerely,<br>
    @if ($signature)<img src="{{ $signature }}" alt="">@endif
    <div class="ln">
        <b>{{ $d['signatory'] }}</b><br>
        {{ $d['signatory_title'] }}, {{ \App\Support\Branding::legalName() }}
    </div>
</div>
