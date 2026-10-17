{{-- Interface picker for a weathermap link: ports with traffic counters of the devices the editor can see, plus one already chosen from elsewhere. --}}
<select name="switch_port_id" class="form-control form-control-sm js-select2" data-placeholder="— No interface (grey link) —" data-allow-clear="true">
    <option value=""></option>
    @foreach ($devices as $sw)
        @if ($sw->ports->isNotEmpty())
            <optgroup label="{{ $sw->isRouter() ? 'Router · ' : '' }}{{ $sw->name }} ({{ $sw->ip }})">
                @foreach ($sw->ports as $p)
                    <option value="{{ $p->id }}" @selected($selected === $p->id)>{{ $portOption($p, $sw) }}</option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
    @if ($otherPorts->isNotEmpty())
        <optgroup label="Already on this map (device outside your access)">
            @foreach ($otherPorts as $p)
                <option value="{{ $p->id }}" @selected($selected === $p->id)>{{ $p->networkSwitch?->name }} · {{ $p->label }}</option>
            @endforeach
        </optgroup>
    @endif
</select>
