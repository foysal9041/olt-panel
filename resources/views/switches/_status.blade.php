@if ($status === 1)
    <span class="badge badge-success">UP</span>
@elseif ($status === 0)
    <span class="badge badge-danger">DOWN</span>
@else
    <span class="badge badge-secondary">UNKNOWN</span>
@endif
