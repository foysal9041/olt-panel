<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Services\DataPruner;
use App\Support\Retention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

/**
 * Settings → Server & HTTPS (the panel's address and certificate, through
 * the root helper install.sh puts in place) and Settings → Data Retention.
 */
class ServerSettingsController extends Controller
{
    // ---- Server & HTTPS -------------------------------------------------------------

    public function server()
    {
        return view('settings.server', [
            'status' => $this->helper(['status'], 20),
            'appUrl' => config('app.url'),
        ]);
    }

    public function address(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:ip,domain',
            'ip' => 'required_if:mode,ip|nullable|ipv4',
            'domain' => ['required_if:mode,domain', 'nullable', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
        ], ['domain.regex' => 'Enter a domain name like panel.example.com (no http://, no slashes).']);
        $host = strtolower($data['mode'] === 'ip' ? $data['ip'] : $data['domain']);

        return $this->run(['address', $host], "Changed the panel's address to $host");
    }

    public function sslOn(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:254']);

        return $this->run(['ssl-on', $data['email']], 'Turned HTTPS on');
    }

    public function sslOff()
    {
        return $this->run(['ssl-off'], 'Turned HTTPS off');
    }

    /** One helper command; its own message (or error) goes back to the page. */
    protected function run(array $args, string $logLine)
    {
        $result = $this->helper($args, 240);
        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['error']);
        }
        ActivityLog::record('updated', $logLine);

        return redirect()->route('settings.server')->with('success', trim($result['output']) ?: 'Done.');
    }

    /**
     * Run the helper through sudo. ok=false (with the reason) when it isn't
     * installed or allowed, or it refused.
     *
     * @return array{ok: bool, output: string, error: ?string, values: array<string, string>}
     */
    protected function helper(array $args, int $timeout): array
    {
        $helper = config('server.admin_helper');
        if (! is_file($helper)) {
            return ['ok' => false, 'output' => '', 'values' => [], 'error' => 'missing'];
        }
        $command = array_merge(config('server.admin_helper_sudo') ? ['sudo', '-n', $helper] : [$helper], $args);
        $result = Process::timeout($timeout)->run($command);
        $output = $result->output();
        $values = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^([a-z_]+)=(.*)$/', $line, $m)) {
                $values[$m[1]] = $m[2];
            }
        }
        $error = null;
        if (! $result->successful()) {
            $err = trim($result->errorOutput()) ?: trim($output);
            $error = str_contains($err, 'sudo') && str_contains($err, 'password') ? 'not-allowed' : preg_replace('/^ERROR:\s*/', '', $err);
        }

        return ['ok' => $result->successful(), 'output' => $output, 'error' => $error, 'values' => $values];
    }

    // ---- Data retention ---------------------------------------------------------------

    public function retention(DataPruner $pruner)
    {
        return view('settings.retention', [
            'r' => Retention::all(),
            'sizes' => $pruner->sizes(),
        ]);
    }

    public function updateRetention(Request $request)
    {
        $rules = ['enabled' => 'nullable|boolean'];
        foreach (Retention::KINDS as $kind => [$label, , , $min, $max]) {
            $rules[$kind] = "required|integer|min:$min|max:$max";
        }
        $data = $request->validate($rules);

        $new = ['enabled' => $request->boolean('enabled')];
        foreach (array_keys(Retention::KINDS) as $kind) {
            $new[$kind] = (int) $data[$kind];
        }
        $before = Retention::all();
        AppSetting::put('retention', $new);
        Retention::flush();

        $changed = array_keys(array_diff_assoc(array_map('intval', $new), array_map('intval', array_intersect_key($before, $new))));
        if ($changed) {
            ActivityLog::record('updated', 'Updated data retention: ' . implode(', ', array_map(fn ($k) => $k . '=' . $new[$k], $changed)));
        }

        return redirect()->route('settings.retention')->with('success', 'Data retention saved.' . ($new['enabled'] ? ' Older data is deleted every night at 03:30.' : ' Auto delete is off — nothing will be deleted.'));
    }

    /** Delete what's past the kept days now, instead of waiting for the night. */
    public function pruneNow(DataPruner $pruner)
    {
        $deleted = array_sum($pruner->run());
        ActivityLog::record('deleted', 'Deleted old history now: ' . number_format($deleted) . ' rows');

        return redirect()->route('settings.retention')->with('success', number_format($deleted) . ' old rows deleted.');
    }
}
