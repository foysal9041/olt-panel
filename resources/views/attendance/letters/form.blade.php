@extends('adminlte::page')

@use('App\Models\HrLetter')
@use('App\Models\Employee')
@php
    $type = $letter->type;
    $d = $letter->data ?? [];
    $editing = $letter->exists;
    $v = fn ($k, $default = null) => old($k, $d[$k] ?? $default);
    [$typeLabel, , $typeIcon, $typeColor] = HrLetter::TYPES[$type];
@endphp

@section('title', $editing ? $letter->ref_no : $typeLabel)

@section('content_header')
<x-attendance.header :title="$editing ? \App\Support\Ui::t('Edit') . ' — ' . $letter->ref_no : $typeLabel" :icon="$typeIcon" :back="route('attendance.letters.index')"
    subtitle="Pick the employee — the details fill in from their record. Change anything for this letter." />
@stop

@section('content')

@include('inventory.partials.alerts')

@unless ($editing)
    <div class="inv-types">
        @foreach (HrLetter::TYPES as $t => [$tLabel, , $tIcon, $tColor])
            <a href="{{ route('attendance.letters.create', array_filter(['type' => $t, 'employee' => $letter->employee_id])) }}" @class(['active' => $t === $type]) style="--c: {{ $tColor }}">
                <i class="{{ $tIcon }}"></i> <span>{{ \App\Support\Ui::t($tLabel) }}</span>
            </a>
        @endforeach
    </div>
@endunless

<form method="POST" action="{{ $editing ? route('attendance.letters.update', $letter) : route('attendance.letters.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    <input type="hidden" name="type" value="{{ $type }}">

    <div class="row">
        <div class="col-lg-8">
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title" style="color: {{ $typeColor }}"><i class="fas fa-user mr-1"></i> Employee</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-md-8 form-group">
                            <label>Employee</label>
                            <select name="employee_id" id="lt-employee" class="form-control" @disabled($editing)>
                                <option value="">— Not in the list (type below) —</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) old('employee_id', $letter->employee_id) === $emp->id)>{{ $emp->name }}{{ $emp->designation ? ' — ' . $emp->designation : '' }}</option>
                                @endforeach
                            </select>
                            @if ($editing && $letter->employee_id)<input type="hidden" name="employee_id" value="{{ $letter->employee_id }}">@endif
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Letter date</label>
                            <input type="date" name="letter_date" value="{{ old('letter_date', $letter->letter_date?->toDateString()) }}" class="form-control" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ $v('name') }}" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Designation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" value="{{ $v('designation') }}" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Department</label>
                            <input type="text" name="department" value="{{ $v('department') }}" class="form-control" maxlength="255" list="lt-depts">
                            <datalist id="lt-depts">@foreach (Employee::DEPARTMENTS as $dep)<option value="{{ $dep }}">@endforeach</datalist>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Emp ID</label>
                            <input type="text" name="emp_id" value="{{ $v('emp_id') }}" class="form-control" maxlength="30">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" value="{{ $v('phone') }}" class="form-control" maxlength="50">
                        </div>
                        <div class="col-12 form-group mb-0">
                            <label>Address</label>
                            <textarea name="address" rows="2" class="form-control" maxlength="500">{{ $v('address') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            @if ($type === 'appointment')
                <div class="card acct-panel">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-file-signature mr-1 text-success"></i> Terms of the job</h3></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-4 form-group">
                                <label>Joining date <span class="text-danger">*</span></label>
                                <input type="date" name="joining_date" value="{{ $v('joining_date') }}" class="form-control" required>
                            </div>
                            <div class="col-md-8 form-group">
                                <label>Workplace</label>
                                <input type="text" name="workplace" value="{{ $v('workplace') }}" class="form-control" maxlength="255">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Basic salary (৳) <span class="text-danger">*</span></label>
                                <input type="number" name="salary_basic" id="lt-basic" value="{{ $v('salary_basic') }}" step="0.01" min="0" class="form-control" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>House rent (৳)</label>
                                <input type="number" name="salary_house_rent" id="lt-rent" value="{{ $v('salary_house_rent') }}" step="0.01" min="0" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Other allowance (৳)</label>
                                <input type="number" name="salary_other" id="lt-other" value="{{ $v('salary_other') }}" step="0.01" min="0" class="form-control">
                            </div>
                            <div class="col-12 mb-3 small text-muted">Gross: <b id="lt-gross">৳0</b> per month</div>
                            <div class="col-md-4 form-group">
                                <label>Probation (months)</label>
                                <input type="number" name="probation_months" value="{{ $v('probation_months') }}" min="0" max="24" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Notice period (days)</label>
                                <input type="number" name="notice_days" value="{{ $v('notice_days') }}" min="0" max="180" class="form-control">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Weekly holiday</label>
                                <input type="text" name="weekly_off" value="{{ $v('weekly_off') }}" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Office hours</label>
                                <input type="text" name="working_hours" value="{{ $v('working_hours') }}" class="form-control" maxlength="100">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Reports to</label>
                                <input type="text" name="reporting_to" value="{{ $v('reporting_to') }}" class="form-control" maxlength="255" placeholder="e.g. Head of NOC">
                            </div>
                            <div class="col-12 form-group mb-0">
                                <label>Other terms <small class="text-muted">(one per line)</small></label>
                                <textarea name="terms" rows="6" class="form-control" maxlength="5000">{{ $v('terms') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="card acct-panel">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-file-excel mr-1 text-danger"></i> Leaving</h3></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-6 form-group">
                                <label>Reason</label>
                                <select name="reason" id="lt-reason" class="form-control" required>
                                    @foreach ($reasons as $k => $rLabel)
                                        <option value="{{ $k }}" @selected($v('reason') === $k)>{{ \App\Support\Ui::t($rLabel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Last working day <span class="text-danger">*</span></label>
                                <input type="date" name="effective_date" value="{{ $v('effective_date') }}" class="form-control" required>
                            </div>
                            <div class="col-md-3 form-group" id="lt-resign">
                                <label>Resignation letter date</label>
                                <input type="date" name="resignation_date" value="{{ $v('resignation_date') }}" class="form-control">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Joined on</label>
                                <input type="date" name="joining_date" value="{{ $v('joining_date') }}" class="form-control">
                            </div>
                            <div class="col-12 form-group">
                                <label>Details <small class="text-muted">(optional — what happened, dates of warnings or absence)</small></label>
                                <textarea name="reason_details" rows="3" class="form-control" maxlength="2000">{{ $v('reason_details') }}</textarea>
                            </div>
                            <div class="col-12 form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="hidden" name="notice_pay" value="0">
                                    <input type="checkbox" class="custom-control-input" id="lt-notice" name="notice_pay" value="1" @checked($v('notice_pay'))>
                                    <label class="custom-control-label font-weight-normal" for="lt-notice">Pay salary in lieu of the notice period</label>
                                </div>
                            </div>
                            <div class="col-12 form-group">
                                <label>Final settlement</label>
                                <textarea name="settlement" rows="2" class="form-control" maxlength="2000">{{ $v('settlement') }}</textarea>
                            </div>
                            <div class="col-12 form-group mb-0">
                                <label>Company property to return</label>
                                <textarea name="property" rows="2" class="form-control" maxlength="1000">{{ $v('property') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-signature mr-1 text-primary"></i> Signed by</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="signatory" value="{{ $v('signatory') }}" class="form-control" maxlength="100" required>
                    </div>
                    <div class="form-group mb-0">
                        <label>Title</label>
                        <input type="text" name="signatory_title" value="{{ $v('signatory_title') }}" class="form-control" maxlength="100" required>
                    </div>
                </div>
            </div>

            <div class="card acct-panel">
                <div class="card-body">
                    @if ($type === 'appointment')
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="lt-update" name="update_employee" value="1" @checked(old('update_employee', ! $editing))>
                            <label class="custom-control-label font-weight-normal" for="lt-update">Also save the designation, joining date and salary to the employee's record</label>
                        </div>
                    @else
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="lt-left" name="mark_left" value="1" @checked(old('mark_left', ! $editing))>
                            <label class="custom-control-label font-weight-normal" for="lt-left">Mark the employee as left — after the last working day they become inactive, their panel login is blocked and their ID card shows as not valid</label>
                        </div>
                    @endif
                    <button class="btn btn-block btn-lg text-white" style="background: {{ $typeColor }}"><i class="fas fa-file-alt"></i> {{ $editing ? \App\Support\Ui::t('Save letter') : \App\Support\Ui::t('Make letter') }}</button>
                    <a href="{{ $editing ? route('attendance.letters.show', $letter) : route('attendance.letters.index') }}" class="btn btn-block btn-light">Cancel</a>
                    @unless ($editing)<small class="form-text text-muted text-center">Reference: {{ $letter->ref_no }} (given when saved)</small>@endunless
                </div>
            </div>
        </div>
    </div>
</form>

@stop

@section('js')
<script>
(function () {
    var emp = document.getElementById('lt-employee');
    if (emp && !emp.disabled) emp.addEventListener('change', function () {
        location.href = @json(route('attendance.letters.create', ['type' => $type])) + (this.value ? '&employee=' + this.value : '');
    });
    var gross = function () {
        var t = ['lt-basic', 'lt-rent', 'lt-other'].reduce(function (s, id) { var e = document.getElementById(id); return s + (e ? parseFloat(e.value) || 0 : 0); }, 0);
        var g = document.getElementById('lt-gross'); if (g) g.textContent = '৳' + t.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };
    ['lt-basic', 'lt-rent', 'lt-other'].forEach(function (id) { var e = document.getElementById(id); if (e) e.addEventListener('input', gross); });
    gross();
    var reason = document.getElementById('lt-reason');
    var resign = function () { document.getElementById('lt-resign').style.display = reason.value === 'resignation' ? '' : 'none'; };
    if (reason) { reason.addEventListener('change', resign); resign(); }
})();
</script>
@stop
