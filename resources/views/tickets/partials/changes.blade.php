{{-- What a timeline line changed: opened, status, who it's given to, progress, a task, edits. --}}
@php $names = ['due_date' => 'Due date', 'title' => 'Task title', 'priority' => 'Priority', 'subject' => 'Subject', 'category' => 'Problem type', 'customer_id' => 'Customer', 'zone' => 'Zone', 'due_at' => 'Fix by']; @endphp
@if ($changes)
    <div class="mt-1">
        @isset($changes['opened'])
            <span class="wk-tl-change"><i class="fas fa-plus-circle text-success"></i> Opened the ticket</span>
        @endisset
        @isset($changes['status'])
            @php [$from, $to] = $changes['status']; @endphp
            <span class="wk-tl-change">
                <span class="wk-pill" style="--c: {{ $statuses[$from][1] ?? '#64748b' }}">{{ \App\Support\Ui::t($statuses[$from][0] ?? $from) }}</span>
                <i class="fas fa-arrow-right text-muted"></i>
                <span class="wk-pill" style="--c: {{ $statuses[$to][1] ?? '#64748b' }}">{{ \App\Support\Ui::t($statuses[$to][0] ?? $to) }}</span>
            </span>
        @endisset
        @isset($changes['assigned_to'])
            <span class="wk-tl-change"><i class="fas fa-user-tag text-primary"></i>
                @if ($changes['assigned_to'][1]) Given to <b>{{ $changes['assigned_to'][1] }}</b> @else Taken off {{ $changes['assigned_to'][0] }} @endif
            </span>
        @endisset
        @isset($changes['progress'])
            <span class="wk-tl-change"><i class="fas fa-tasks text-primary"></i> {{ $changes['progress'][0] }}% → <b>{{ $changes['progress'][1] }}%</b></span>
        @endisset
        @isset($changes['task'])
            <span class="wk-tl-change"><i class="fas fa-clipboard-check text-primary"></i> Task “{{ $changes['task'][0] }}” for <b>{{ $changes['task'][1] }}</b></span>
        @endisset
        @isset($changes['edited'])
            <span class="wk-tl-change"><i class="fas fa-pen text-muted"></i> Edited: {{ collect($changes['edited'])->map(fn ($f) => \App\Support\Ui::t($names[$f] ?? $f))->implode(', ') }}</span>
        @endisset
        @foreach (['title', 'due_date', 'priority'] as $f)
            @isset($changes[$f])
                <span class="wk-tl-change"><i class="fas fa-pen text-muted"></i> {{ \App\Support\Ui::t($names[$f]) }}: {{ $changes[$f][1] ?? '—' }}</span>
            @endisset
        @endforeach
    </div>
@endif
