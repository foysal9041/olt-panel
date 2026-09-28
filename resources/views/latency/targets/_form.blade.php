@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6 form-group">
        <label>Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $target->name) }}"
               placeholder="e.g. Google DNS, BDIX Gateway" required autofocus>
    </div>

    <div class="col-md-6 form-group">
        <label>Destination IP / Hostname</label>
        <input type="text" name="host" class="form-control" value="{{ old('host', $target->host) }}"
               placeholder="e.g. 8.8.8.8 or google.com" required>
    </div>

    <div class="col-md-6 form-group">
        <label>Group <small class="text-muted">(optional)</small></label>
        <input type="text" name="group" class="form-control" list="latency-groups"
               value="{{ old('group', $target->group) }}" placeholder="e.g. Upstream, BDIX, Clients">
        <datalist id="latency-groups">
            @foreach ($groups as $group)
                <option value="{{ $group }}">
            @endforeach
        </datalist>
    </div>

    <div class="col-md-6 form-group">
        <label>Pings per probe</label>
        <input type="number" name="pings" class="form-control" min="5" max="50"
               value="{{ old('pings', $target->pings) }}" required>
        <small class="form-text text-muted">Sent every minute. 20 is the SmokePing default.</small>
    </div>

    <div class="col-12">
        <hr class="mt-0">
        <h6 class="font-weight-bold mb-1"><i class="fas fa-bell text-danger"></i> Alert thresholds</h6>
        <p class="small text-muted mb-3">
            When the median latency or packet loss stays past a threshold for {{ \App\Models\LatencyTarget::ALERT_AFTER }} probes
            in a row, a Telegram alert is sent and the graph turns red. Another alert is sent when it recovers.
            Leave both blank for no alerts.
        </p>
    </div>

    <div class="col-md-6 form-group">
        <label>Latency threshold (ms)</label>
        <div class="input-group">
            <input type="number" step="0.1" min="0.1" name="latency_threshold" class="form-control"
                   value="{{ old('latency_threshold', $target->latency_threshold) }}" placeholder="e.g. 100">
            <div class="input-group-append"><span class="input-group-text">ms</span></div>
        </div>
        <small class="form-text text-muted">Alert when median RTT goes above this.</small>
    </div>

    <div class="col-md-6 form-group">
        <label>Packet loss threshold (%)</label>
        <div class="input-group">
            <input type="number" step="1" min="1" max="100" name="loss_threshold" class="form-control"
                   value="{{ old('loss_threshold', $target->loss_threshold) }}" placeholder="e.g. 20">
            <div class="input-group-append"><span class="input-group-text">%</span></div>
        </div>
        <small class="form-text text-muted">Alert when loss reaches this. 100 = only when fully down.</small>
    </div>

    <div class="col-12 mb-3">
        <div class="custom-control custom-switch">
            <input type="hidden" name="notify" value="0">
            <input type="checkbox" class="custom-control-input" id="notify" name="notify" value="1"
                   @checked(old('notify', $target->notify))>
            <label class="custom-control-label" for="notify">Send Telegram alerts for this target</label>
        </div>
        <small class="form-text text-muted">
            Uses the bot and chat IDs from <strong>Settings → Telegram</strong>.
        </small>
    </div>

    <div class="col-12 form-group">
        <label>Description <small class="text-muted">(optional)</small></label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $target->description) }}</textarea>
    </div>

    <div class="col-12">
        <div class="custom-control custom-switch">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $target->is_active))>
            <label class="custom-control-label" for="is_active">Active (ping this target)</label>
        </div>
    </div>
</div>
