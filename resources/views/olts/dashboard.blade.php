@extends('adminlte::page')

@section('title', 'OLT Dashboard')

@section('content_header')
<h1>OLT Dashboard</h1>
@stop

@section('content')

<div class="row">

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-network-wired"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total OLT</span>
                <span class="info-box-number">{{ $totalOlt }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Online OLT</span>
                <span class="info-box-number">{{ $onlineOlt }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-times"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Offline OLT</span>
                <span class="info-box-number">{{ $offlineOlt }}</span>
            </div>
        </div>
    </div>

</div>

<div class="card card-outline card-primary">

    <div class="card-header">
        <h3 class="card-title">Live OLT Status</h3>
        <div class="card-tools">
            <a href="{{ route('olt.index') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-list"></i> View All OLTs
            </a>
        </div>
    </div>

    <div class="card-body">

        <div class="row">

            @forelse($olts as $olt)

            <div class="col-lg-3 col-md-4 col-sm-6">

                <div class="card mb-2">

                    <div class="card-body p-2">

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <strong>
                                {{ $olt->name }}
                            </strong>

                            @if($olt->status)

                                <span class="badge badge-success">
                                    LIVE
                                </span>

                            @else

                                <span class="badge badge-danger">
                                    DOWN
                                </span>

                            @endif

                        </div>

                        <div class="mb-1">
                            <strong>Zone:</strong>
                            {{ $olt->zone }}
                        </div>

                        <div>
                            <strong>IP:</strong>
                            {{ $olt->ip }}
                        </div>

                    </div>

                </div>

            </div>

            @empty

            <div class="col-12 text-center text-muted py-4">
                No OLT Found
            </div>

            @endforelse

        </div>

    </div>

</div>

@stop

@section('js')

<script>
setInterval(function () {
    location.reload();
}, 30000);
</script>

@stop
