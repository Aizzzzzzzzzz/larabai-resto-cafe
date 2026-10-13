<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AuditArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Every important action, filterable by period, area and person, and exportable. */
class AuditLogController extends Controller
{
    public const RANGES = ['today' => 'Today', '7' => 'Last 7 days', 'all' => 'All'];

    private const PAGE = 100;

    public function index(Request $request): View
    {
        $limit = max(self::PAGE, (int) $request->query('limit', self::PAGE));
        $total = $this->filtered($request)->count();

        $rows = $this->filtered($request)->limit($limit)->get()->map(fn (AuditLog $a) => [
            'logged_at' => $a->logged_at->toDateTimeString(),
            'user' => $a->user_name,
            'role' => $a->role,
            'action' => $a->action,
            'detail' => (string) $a->detail,
            'area' => AuditArea::of($a->action),
            'role_label' => Role::tryFrom($a->role)?->label() ?? 'Customer',
        ]);

        return view('admin.audit-logs', [
            'range' => $this->range($request),
            'area' => (string) $request->query('area'),
            'user' => (string) $request->query('user'),
            'q' => trim((string) $request->query('q')),
            'areas' => AuditArea::NAMES,
            'users' => AuditLog::distinct()->orderBy('user_name')->pluck('user_name'),
            'total' => $total,
            'rows' => $rows,
            'nextLimit' => $total > $limit ? $limit + self::PAGE : null,
        ]);
    }

    /** CSV of the log with the list's current filters (opens in Excel: UTF-8 with a byte-order mark). */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request);
        $name = 'brew-bite-audit-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'User', 'Role', 'Area', 'Action', 'Details']);
            foreach ($query->cursor() as $a) {
                fputcsv($out, array_map([self::class, 'safeCell'], [$a->logged_at->toDateTimeString(), $a->user_name, $a->role, AuditArea::of($a->action)[0], $a->action, (string) $a->detail]));
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

    /** The log, newest first, with the screen's filters applied. @return Builder<AuditLog> */
    private function filtered(Request $request): Builder
    {
        $area = (string) $request->query('area');
        $user = (string) $request->query('user');
        $q = trim((string) $request->query('q'));

        return AuditLog::query()
            ->when($this->range($request) === 'today', fn (Builder $b) => $b->where('logged_at', '>=', now()->startOfDay()))
            ->when($this->range($request) === '7', fn (Builder $b) => $b->where('logged_at', '>=', now()->subDays(7)))
            ->when($area !== '', fn (Builder $b) => $b->where('module', $area))
            ->when($user !== '', fn (Builder $b) => $b->where('user_name', $user))
            ->when($q !== '', function (Builder $b) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $b->where(fn (Builder $w) => $w->where('action', 'like', $like)->orWhere('detail', 'like', $like)->orWhere('user_name', 'like', $like));
            })
            ->orderByDesc('logged_at')->orderByDesc('id');
    }
}
