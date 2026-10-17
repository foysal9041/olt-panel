<?php

namespace App\Menu;

use App\Models\NetworkSwitch;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

/**
 * Switches and routers share their pages (switches/{id}…), so the sidebar
 * can't tell them apart by URL: menu items with a "device_type" key are
 * marked active from the device the current page is about.
 */
class DeviceTypeFilter implements FilterInterface
{
    /** "switch", "router", or null when the page isn't about one kind (e.g. the all-devices list). */
    public static function current(): ?string
    {
        $route = request()->route();
        $name = (string) $route?->getName();
        if (str_starts_with($name, 'routers.')) {
            return 'router';
        }
        if (! str_starts_with($name, 'switches.')) {
            return null;
        }

        $device = $route->parameter('switch');
        if ($device !== null) {
            $type = $device instanceof NetworkSwitch ? $device->device_type : NetworkSwitch::whereKey($device)->value('device_type');

            return $type === 'router' ? 'router' : 'switch';
        }

        return match (request()->query('type')) {
            'router' => 'router',
            'all' => null,
            default => 'switch',
        };
    }

    public function transform($item)
    {
        if (isset($item['device_type'])) {
            $item['active'] = self::current() === $item['device_type'];
        }

        return $item;
    }
}
