<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Support\AuditArea;
use App\Support\DemoAudit;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Every important action, filterable by period, area and person, and exportable. */
class AuditLogController extends Controller
{
    public const RANGES = ['today' => 'Today', '7' => 'Last 7 days', 'all' => 'All'];

    private const PAGE = 100;

    public function index(Request $request): View
    {
        $all = collect(DemoAudit::all());
        $entries = $this->filtered($request, $all);
        $limit = max(self::PAGE, (int) $request->query('limit', self::PAGE));

        return view('admin.audit-logs', [
            'range' => $this->range($request),
            'area' => (string) $request->query('area'),
            'user' => (string) $request->query('user'),
            'q' => trim((string) $request->query('q')),
            'areas' => AuditArea::NAMES,
            'users' => $all->pluck('user')->unique()->sort()->values(),
            'total' => $entries->count(),
            'rows' => $entries->take($limit)->map(fn ($a) => $a + [
                'area' => AuditArea::of($a['action']),
                'role_label' => Role::tryFrom($a['role'])?->label() ?? 'Customer',
            ])->values(),
            'nextLimit' => $entries->count() > $limit ? $limit + self::PAGE : null,
        ]);
    }

    /** CSV of the log with the list's current filters (opens in Excel: UTF-8 with a byte-order mark). */
    public function export(Request $request): StreamedResponse
    {
        $entries = $this->filtered($request, collect(DemoAudit::all()));
        $name = 'brew-bite-audit-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'User', 'Role', 'Area', 'Action', 'Details']);
            foreach ($entries as $a) {
                fputcsv($out, array_map([self::class, 'safeCell'], [$a['logged_at'], $a['user'], $a['role'], AuditArea::of($a['action'])[0], $a['action'], $a['detail']]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A cell that starts with = + - or @ would run as a formula in a spreadsheet, so it's prefixed with an apostrophe. */
    public static function safeCell(string $value): string
    {
        return $value !== '' && str_contains('=+-@', $value[0]) ? "'".$value : $value;
    }

    private function range(Request $request): string
    {
        return array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '7';
    }

    /** Newest first. */
    private function filtered(Request $request, Collection $all): Collection
    {
        $range = $this->range($request);
        $area = (string) $request->query('area');
        $user = (string) $request->query('user');
        $q = Str::lower(trim((string) $request->query('q')));

        return $all
            ->filter(fn ($a) => match ($range) {
                'today' => Carbon::parse($a['logged_at'])->isToday(),
                '7' => Carbon::parse($a['logged_at'])->greaterThanOrEqualTo(now()->subDays(7)),
                default => true,
            })
            ->filter(fn ($a) => $area === '' || AuditArea::of($a['action'])[0] === $area)
            ->filter(fn ($a) => $user === '' || $a['user'] === $user)
            ->filter(fn ($a) => $q === '' || Str::contains(Str::lower($a['action'].' '.$a['detail'].' '.$a['user']), $q))
            ->sortByDesc('logged_at')
            ->values();
    }
}
