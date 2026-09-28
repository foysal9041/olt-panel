<div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
        <thead>
            <tr>
                <th class="pl-3">Time</th>
                <th>Event</th>
                @if ($showSwitch) <th>Switch</th> @endif
                <th>Details</th>
                <th class="text-center">Telegram</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($events as $event)
                @php([$label, $color] = \App\Models\SwitchEvent::TYPES[$event->type] ?? [$event->type, 'secondary'])
                <tr>
                    <td class="pl-3 text-nowrap">
                        {{ $event->occurred_at->format('d M Y, h:i:s A') }}
                        <div class="small text-muted">{{ $event->occurred_at->diffForHumans() }}</div>
                    </td>
                    <td><span class="badge badge-{{ $color }}">{{ $label }}</span></td>
                    @if ($showSwitch)
                        <td>
                            @if ($event->networkSwitch)
                                <a href="{{ route('switches.show', $event->networkSwitch) }}">{{ $event->networkSwitch->name }}</a>
                            @endif
                        </td>
                    @endif
                    <td>{{ $event->message }}</td>
                    <td class="text-center">
                        @if ($event->notified)
                            <i class="fab fa-telegram-plane text-info" title="Sent"></i>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showSwitch ? 5 : 4 }}" class="text-center text-muted py-3">No events yet</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
