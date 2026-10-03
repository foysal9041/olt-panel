@extends('adminlte::page')

@section('title', 'HR Letters')

@use('App\Models\HrLetter')

@section('content_header')
<x-attendance.header title="HR Letters" icon="fas fa-file-signature" subtitle="Appointment and termination letters on the company pad — each one kept with its reference number">
    <a href="{{ route('attendance.letters.create', ['type' => 'appointment']) }}" class="btn btn-success btn-sm"><i class="fas fa-file-signature"></i> Appointment letter</a>
    <a href="{{ route('attendance.letters.create', ['type' => 'termination']) }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-excel"></i> Termination letter</a>
</x-attendance.header>
@stop

@section('content')

@include('inventory.partials.alerts')

<div class="inv-types">
    @foreach (HrLetter::TYPES as $t => [$tLabel, , $tIcon, $tColor])
        <a href="{{ route('attendance.letters.index', array_filter(['type' => request('type') === $t ? null : $t, 'employee' => request('employee')])) }}" @class(['active' => request('type') === $t]) style="--c: {{ $tColor }}">
            <i class="{{ $tIcon }}"></i>
            <span>{{ \App\Support\Ui::t($tLabel) }}<br><small class="text-muted">{{ $counts[$t] ?? 0 }}</small></span>
        </a>
    @endforeach
</div>

<div class="card acct-panel">
    <div class="card-header flex-wrap" style="gap:.5rem">
        <form method="GET" class="form-inline" style="gap:.4rem">
            @if (request('type'))<input type="hidden" name="type" value="{{ request('type') }}">@endif
            <select name="employee" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All employees</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected(request('employee') == $emp->id)>{{ $emp->name }}</option>
                @endforeach
            </select>
        </form>
        <span class="small text-muted">{{ $letters->total() }} {{ \App\Support\Ui::t(Str::plural('letter', $letters->total())) }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table inv-table mb-0">
                <thead><tr><th class="pl-3">Date</th><th>Letter</th><th>Employee</th><th>Ref</th><th>Made by</th><th class="pr-3"></th></tr></thead>
                <tbody>
                    @forelse ($letters as $l)
                        @php [, , $lIcon, $lColor] = HrLetter::TYPES[$l->type] ?? ['', '', 'fas fa-file', '#64748b']; @endphp
                        <tr>
                            <td class="pl-3 text-nowrap">{{ $l->letter_date->format('d M Y') }}</td>
                            <td><span class="inv-type" style="--c: {{ $lColor }}"><i class="{{ $lIcon }}"></i> {{ $l->typeLabel() }}</span>
                                @if ($l->type === 'termination')<div class="inv-sub">{{ \App\Support\Ui::t(HrLetter::REASONS[$l->data['reason'] ?? 'other'] ?? '') }} · {{ \Illuminate\Support\Carbon::parse($l->data['effective_date'])->format('d M Y') }}</div>@endif
                            </td>
                            <td>
                                <span class="inv-name">{{ $l->name() }}</span>
                                <div class="inv-sub">{{ $l->data['designation'] ?? '' }}</div>
                            </td>
                            <td class="small text-nowrap">{{ $l->ref_no }}</td>
                            <td class="small text-nowrap">{{ $l->creator?->name ?? '—' }}</td>
                            <td class="pr-3 text-right text-nowrap">
                                <a href="{{ route('attendance.letters.show', $l) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-print"></i> Print</a>
                                <a href="{{ route('attendance.letters.show', ['letter' => $l, 'download' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                                <a href="{{ route('attendance.letters.edit', $l) }}" class="btn btn-sm btn-light border" title="Edit"><i class="fas fa-pen"></i></a>
                                @if (auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('attendance.letters.destroy', $l) }}" class="d-inline js-confirm-delete" data-confirm-message="Remove this letter?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-link text-danger" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="acct-empty"><i class="fas fa-file-signature"></i>No letters yet — make an appointment or termination letter above.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($letters->hasPages())
        <div class="card-footer">{{ $letters->links() }}</div>
    @endif
</div>

@stop
