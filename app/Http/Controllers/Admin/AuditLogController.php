<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /** Hard cap on rows per CSV export. */
    private const EXPORT_LIMIT = 50000;

    public function index(Request $request)
    {
        $this->authorize('view-audit-logs');

        $logs = $this->filtered($request)
            ->with('user')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $modules = AuditLog::query()->distinct()->orderBy('module')->pluck('module')->filter()->values();
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action')->filter()->values();
        $users   = User::orderBy('name')->get(['id', 'name']);

        $filterUser = $request->filled('user_id') ? $users->firstWhere('id', (int) $request->input('user_id')) : null;

        return view('admin.audit-logs.index', compact('logs', 'modules', 'actions', 'users', 'filterUser'));
    }

    /**
     * CSV export of the currently filtered log (throttled at the route).
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('view-audit-logs');

        $query = $this->filtered($request)->latest();

        AuditLogService::log(
            'exported',
            'audit-logs',
            'Exported audit log CSV' . ($request->except('page') ? ' (filters: ' . http_build_query($request->except('page')) . ')' : '')
        );

        $filename = 'audit-log-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['Date/Time', 'User ID', 'User', 'Action', 'Module', 'Description', 'IP Address', 'User Agent']);

            foreach ($query->limit(self::EXPORT_LIMIT)->cursor() as $log) {
                fputcsv($out, array_map(fn ($v) => $this->csvCell($v), [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->user_id,
                    $log->user_name,
                    $log->action,
                    $log->module,
                    $log->description,
                    $log->ip_address,
                    $log->user_agent,
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        $request->validate([
            'search'    => ['nullable', 'string', 'max:200'],
            'module'    => ['nullable', 'string', 'max:50'],
            'action'    => ['nullable', 'string', 'max:50'],
            'user_id'   => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
        ]);

        return AuditLog::query()
            ->when($request->filled('search'),    fn ($q) => $q->where('description', 'like', '%' . $request->input('search') . '%'))
            ->when($request->filled('module'),    fn ($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('action'),    fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('user_id'),   fn ($q) => $q->where('user_id', (int) $request->input('user_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')));
    }

    /** Neutralise spreadsheet formula injection. */
    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
