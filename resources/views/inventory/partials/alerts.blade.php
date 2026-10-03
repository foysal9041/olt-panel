@if (session('success')) <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div> @endif
@if (session('error')) <div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div> @endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
