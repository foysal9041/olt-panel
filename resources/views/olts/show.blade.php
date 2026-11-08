@extends('adminlte::page')

@section('title', 'OLT Credentials')

@section('content_header')
<x-noc.header title="{{ $olt->name }}" subtitle="Device details and credentials · {{ $olt->ip }}" :back="route('olt.index')" />
@stop

@section('content')

<div class="card card-outline card-warning">

    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-network-wired mr-1"></i>
            {{ $olt->name }}
        </h3>
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Identification</h6>

                <div class="form-group">
                    <label>Zone</label>
                    <input type="text" class="form-control" value="{{ $olt->zone }}" readonly>
                </div>

                <div class="form-group">
                    <label>OLT Name</label>
                    <input type="text" class="form-control" value="{{ $olt->name }}" readonly>
                </div>

                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" class="form-control" value="{{ $olt->brand }}" readonly>
                </div>

                <div class="form-group">
                    <label>VLAN</label>
                    <input type="text" class="form-control" value="{{ $olt->vlan }}" readonly>
                </div>

            </div>

            <div class="col-md-6">

                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Connection &amp; Credentials</h6>

                <div class="form-group">
                    <label>IP Address</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="{{ $olt->ip }}" readonly id="field-ip">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary js-copy" data-target="field-ip">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="{{ $olt->username }}" readonly id="field-username">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary js-copy" data-target="field-username">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                @php $canReveal = ! auth()->user()->isViewer(); @endphp
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="••••••••" readonly id="field-password" data-secret="password">
                        @if ($canReveal)
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary js-toggle-password" data-target="field-password" title="Show">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary js-copy" data-target="field-password" title="Copy">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                    <small class="form-text text-muted">
                        @if ($canReveal)
                            Stored encrypted and not in this page; showing or copying it asks for your panel password and is logged.
                        @else
                            Hidden for view-only accounts.
                        @endif
                    </small>
                </div>

                <div class="form-group">
                    <label>SNMP Community</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="{{ filled($olt->snmp) ? '••••••••' : '' }}" placeholder="Not set" readonly id="field-snmp" data-secret="snmp">
                        <div class="input-group-append">
                            @if ($canReveal && filled($olt->snmp))
                                <button type="button" class="btn btn-outline-secondary js-toggle-password" data-target="field-snmp" title="Show">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary js-copy" data-target="field-snmp" title="Copy">
                                    <i class="fas fa-copy"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <small id="copy-feedback" class="text-success font-weight-bold d-none">
                    <i class="fas fa-check"></i> Copied
                </small>

            </div>

        </div>

    </div>

    <div class="card-footer">

        <a href="{{ route('olt.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to List
        </a>

        <a href="{{ route('olt.edit',$olt->id) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit OLT
        </a>

    </div>

</div>

{{-- Asked before the login details are fetched (at most every 15 minutes). --}}
<div class="modal fade" id="secret-confirm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <form class="modal-content" id="secret-form">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-lock mr-1"></i> Confirm it's you</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <label class="small mb-1" for="secret-password">Your panel password</label>
                <input type="password" class="form-control" id="secret-password" autocomplete="current-password" required>
                <div class="invalid-feedback d-block" id="secret-error"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Show</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var revealUrl = @json(route('olt.reveal', $olt));
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var secrets = null, waiting = null;

    // The login details from the server: asks for the user's password when the server wants it.
    function load(password) {
        return fetch(revealUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(password ? { current_password: password } : {}),
        }).then(function (r) {
            if (r.ok) return r.json().then(function (d) { secrets = d; return d; });
            return r.json().catch(function () { return {}; }).then(function (d) {
                var msg = (d.errors && d.errors.current_password && d.errors.current_password[0]) || d.message || 'Not allowed.';
                if (r.status === 422) throw { ask: true, message: password ? msg : '' };
                throw { ask: false, message: r.status === 429 ? uiText('Too many tries — wait a minute.', 'অনেকবার চেষ্টা হয়েছে — এক মিনিট পরে আবার চেষ্টা করুন।') : msg };
            });
        });
    }
    function withSecrets(then) {
        if (secrets) { then(secrets); return; }
        load().then(then).catch(function (e) {
            if (!e.ask) { alert(e.message); return; }
            waiting = then;
            document.getElementById('secret-error').textContent = '';
            document.getElementById('secret-password').value = '';
            $('#secret-confirm').modal('show');
        });
    }
    document.getElementById('secret-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        load(document.getElementById('secret-password').value).then(function (d) {
            $('#secret-confirm').modal('hide');
            if (waiting) waiting(d);
        }).catch(function (e) { document.getElementById('secret-error').textContent = e.message || uiText('Wrong password.', 'ভুল পাসওয়ার্ড।'); });
    });
    $('#secret-confirm').on('shown.bs.modal', function () { document.getElementById('secret-password').focus(); });

    function valueOf(input) { return input.dataset.secret ? (secrets ? secrets[input.dataset.secret] || '' : null) : input.value; }

    document.querySelectorAll('.js-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                withSecrets(function () {
                    input.value = valueOf(input);
                    input.type = 'text';
                    icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
                });
            } else {
                input.type = 'password';
                input.value = '••••••••';
                icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
            }
        });
    });

    document.querySelectorAll('.js-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var copy = function () {
                navigator.clipboard.writeText(valueOf(input)).then(function () {
                    var feedback = document.getElementById('copy-feedback');
                    feedback.classList.remove('d-none');
                    setTimeout(function () {
                        feedback.classList.add('d-none');
                    }, 1500);
                });
            };
            if (input.dataset.secret) withSecrets(copy); else copy();
        });
    });
})();
</script>

@stop
