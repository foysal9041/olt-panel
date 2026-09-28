{{-- Link status from the every-minute monitor. Expects $link. --}}
@if ($link->status !== 'active')
    <span class="badge badge-secondary">INACTIVE</span>
    <div class="small text-muted">not monitored</div>
@elseif (! $link->pingTarget())
    <span class="badge badge-light border">NO IP</span>
    <div class="small text-muted">add a Peering / Ping IP</div>
@elseif ($link->link_state === null)
    <span class="badge badge-secondary">CHECKING</span>
    <div class="small text-muted">first result within a minute</div>
@elseif ($link->link_state === 1)
    <span class="badge badge-success">UP</span>
    @if ($link->last_ping_ok)
        <small class="text-muted text-nowrap">{{ $link->last_ping_rtt }} ms{{ $link->last_ping_loss > 0 ? ' · ' . round($link->last_ping_loss) . '% loss' : '' }}</small>
    @endif
    <div class="small text-muted" title="Up since {{ $link->state_changed_at?->format('d M Y, h:i A') }} · checked {{ $link->last_ping_at?->diffForHumans() }}">
        up {{ $link->state_changed_at?->diffForHumans(null, true) }}
        @if ($link->state_streak > 0) <i class="fas fa-exclamation-circle text-warning" title="Last check got no reply — confirming"></i> @endif
    </div>
@else
    <span class="badge badge-danger">DOWN</span>
    <div class="small text-danger" title="Down since {{ $link->state_changed_at?->format('d M Y, h:i A') }} · checked {{ $link->last_ping_at?->diffForHumans() }}">
        down {{ $link->state_changed_at?->diffForHumans(null, true) }}
    </div>
@endif
