@extends('adminlte::page')

@use('App\Models\Ticket')
@php
    $editing = $ticket->exists;
    $customerData = $customers->mapWithKeys(fn ($c) => [$c->id => ['zone' => $c->zone, 'phone' => $c->phone, 'contact' => $c->contact_person]]);
@endphp

@section('title', $editing ? $ticket->number() : 'New Ticket')

@section('content_header')
<x-work.header :title="$editing ? $ticket->number() . ' — ' . \App\Support\Ui::t('Edit') : 'New Ticket'" icon="fas fa-ticket-alt"
    :back="$editing ? route('tickets.show', $ticket) : route('tickets.index')"
    subtitle="What's wrong, for whom, how urgent — and who will fix it" />
@stop

@section('content')

@include('inventory.partials.alerts')

<form method="POST" action="{{ $editing ? route('tickets.update', $ticket) : route('tickets.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-circle mr-1 text-danger"></i> The problem</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Problem type</label>
                        <div class="wk-status-pick">
                            @foreach (Ticket::CATEGORIES as $k => [$cLabel, $cIcon])
                                <label style="--c:#4f46e5">
                                    <input type="radio" name="category" value="{{ $k }}" @checked(old('category', $ticket->category) === $k)>
                                    <span><i class="{{ $cIcon }}"></i> {{ \App\Support\Ui::t($cLabel) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" value="{{ old('subject', $ticket->subject) }}" class="form-control" maxlength="255" required
                               placeholder="e.g. Navaron zone — no internet since morning">
                    </div>
                    <div class="form-group mb-0">
                        <label>Details</label>
                        <textarea name="description" rows="5" class="form-control" maxlength="10000" placeholder="{{ \App\Support\Ui::t("What the customer said, what's been checked, ONU / port / signal…") }}">{{ old('description', $ticket->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-user mr-1 text-primary"></i> Customer / place</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Customer</label>
                            <select name="customer_id" id="tk-customer" class="form-control">
                                <option value="">— none / not a customer —</option>
                                @foreach ($customers->groupBy('customer_type') as $type => $group)
                                    <optgroup label="{{ \App\Support\Ui::t(\App\Models\Customer::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type))) }}">
                                        @foreach ($group as $c)
                                            <option value="{{ $c->id }}" @selected(old('customer_id', $ticket->customer_id) == $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Zone</label>
                            <select name="zone" id="tk-zone" class="form-control">
                                <option value="">—</option>
                                @foreach ($zones as $z)
                                    <option value="{{ $z }}" @selected(old('zone', $ticket->zone) === $z)>{{ $z }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-md-0">
                            <label>Contact name</label>
                            <input type="text" name="contact_name" id="tk-contact" value="{{ old('contact_name', $ticket->contact_name) }}" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Contact phone</label>
                            <input type="text" name="contact_phone" id="tk-phone" value="{{ old('contact_phone', $ticket->contact_phone) }}" class="form-control" maxlength="30" placeholder="01XXXXXXXXX">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-flag mr-1 text-warning"></i> Priority &amp; who fixes it</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Priority</label>
                        <div class="wk-status-pick">
                            @foreach (Ticket::PRIORITIES as $k => [$pLabel, $pColor, $hours])
                                <label style="--c: {{ $pColor }}" title="{{ \App\Support\Ui::t('Fix within') }} {{ $hours }}h">
                                    <input type="radio" name="priority" value="{{ $k }}" data-hours="{{ $hours }}" @checked(old('priority', $ticket->priority) === $k)>
                                    <span>{{ \App\Support\Ui::t($pLabel) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <small class="form-text text-muted">Urgent 4h · High 8h · Normal 24h · Low 3 days</small>
                    </div>
                    <div class="form-group">
                        <label>Give to</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">— not yet —</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('assigned_to', $ticket->assigned_to) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fix by</label>
                        <input type="datetime-local" name="due_at" value="{{ old('due_at', $ticket->due_at?->format('Y-m-d\TH:i')) }}" class="form-control">
                        <small class="form-text text-muted">Empty = from the priority.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label>Came by</label>
                        <select name="source" class="form-control">
                            <option value="">—</option>
                            @foreach (Ticket::SOURCES as $k => $sLabel)
                                <option value="{{ $k }}" @selected(old('source', $ticket->source) === $k)>{{ \App\Support\Ui::t($sLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card acct-panel">
                <div class="card-body">
                    <button class="btn btn-primary btn-block btn-lg"><i class="fas fa-save"></i> {{ $editing ? \App\Support\Ui::t('Save') : \App\Support\Ui::t('Open ticket') }}</button>
                    <a href="{{ $editing ? route('tickets.show', $ticket) : route('tickets.index') }}" class="btn btn-light btn-block">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

@stop

@section('js')
<script>
(function () {
    var customers = @json($customerData);
    $('#tk-customer').on('change', function () {
        var c = customers[this.value];
        if (!c) return;
        if (c.zone && $('#tk-zone option[value="' + c.zone.replace(/"/g, '\\"') + '"]').length) $('#tk-zone').val(c.zone).trigger('change');
        if (c.phone && !$('#tk-phone').val()) $('#tk-phone').val(c.phone);
        if (c.contact && !$('#tk-contact').val()) $('#tk-contact').val(c.contact);
    });
})();
</script>
@stop
