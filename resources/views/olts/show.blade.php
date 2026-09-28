@extends('adminlte::page')

@section('title', 'OLT Credentials')

@section('content_header')
    <h1>Device Credentials</h1>
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
                    <label>Model</label>
                    <input type="text" class="form-control" value="{{ $olt->model }}" readonly>
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

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" value="{{ $olt->password }}" readonly id="field-password">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary js-toggle-password" data-target="field-password">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary js-copy" data-target="field-password">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>SNMP Community</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="{{ $olt->snmp }}" readonly id="field-snmp">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary js-copy" data-target="field-snmp">
                                <i class="fas fa-copy"></i>
                            </button>
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

<script>

document.querySelectorAll('.js-toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);
        var icon = btn.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

document.querySelectorAll('.js-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);

        navigator.clipboard.writeText(input.value).then(function () {
            var feedback = document.getElementById('copy-feedback');
            feedback.classList.remove('d-none');
            setTimeout(function () {
                feedback.classList.add('d-none');
            }, 1500);
        });
    });
});

</script>

@stop
