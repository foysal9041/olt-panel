<?php

namespace App\Menu;

use App\Models\Leave;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

/**
 * Counts on the sidebar's "My Work" items: open tasks given to me, active
 * tickets given to me, and leave requests waiting for HR.
 */
class WorkBadgeFilter implements FilterInterface
{
    public function transform($item)
    {
        $key = $item['key'] ?? null;
        if (! in_array($key, ['my-tasks', 'tickets', 'leave-requests'], true) || ! auth()->check()) {
            return $item;
        }

        // Worked out once per request (the filter itself may live longer).
        $attributes = request()->attributes;
        if (! $attributes->has('work_badges')) {
            $attributes->set('work_badges', $this->counts());
        }
        [$count, $color] = $attributes->get('work_badges')[$key];
        if ($count > 0) {
            $item['label'] = $count;
            $item['label_color'] = $color;
        }

        return $item;
    }

    protected function counts(): array
    {
        $user = auth()->user();
        $overdue = Task::where('assigned_to', $user->id)->open()->whereDate('due_date', '<', today())->exists();

        return [
            'my-tasks' => [Task::where('assigned_to', $user->id)->open()->count(), $overdue ? 'danger' : 'info'],
            'tickets' => [Ticket::where('assigned_to', $user->id)->active()->count(), 'danger'],
            'leave-requests' => [Gate::allows('access-attendance-leaves') ? Leave::where('status', 'pending')->count() : 0, 'warning'],
        ];
    }
}
