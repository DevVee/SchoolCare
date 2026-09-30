<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    /* ------------------------------------------------------------------ */
    /*  SMS LOG INDEX                                                        */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-sms');

        $search   = $request->get('search', '');
        $status   = $request->get('status', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo   = $request->get('date_to', '');

        $logs = SmsLog::with('createdBy')
            // Grouped so the OR does not bypass the status/date filters below.
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('recipient_number', 'like', "%{$search}%")
                ->orWhere('recipient_name', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->when($status,   fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $filters = compact('search', 'status', 'dateFrom', 'dateTo');

        // Summary numbers for this month (stat cards). One grouped query, no provider API call.
        $monthCounts = SmsLog::where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $stats = [
            'sent'    => (int) ($monthCounts['sent'] ?? 0),
            'failed'  => (int) ($monthCounts['failed'] ?? 0),
            'skipped' => (int) ($monthCounts['skipped'] ?? 0),
            'pending' => (int) ($monthCounts['pending'] ?? 0),
        ];

        return view('sms.index', compact('logs', 'filters', 'stats'));
    }

    /* ------------------------------------------------------------------ */
    /*  MANUAL SEND FORM                                                     */
    /* ------------------------------------------------------------------ */
    public function create()
    {
        $this->authorize('send-sms');
        return view('sms.create');
    }

    /* ------------------------------------------------------------------ */
    /*  SEND                                                                 */
    /* ------------------------------------------------------------------ */
    public function send(Request $request)
    {
        $this->authorize('send-sms');

        $validated = $request->validate([
            'recipient_number' => ['required', 'string', 'max:20', function ($attribute, $value, $fail) {
                if (! $this->sms->normalizeNumber($value)) {
                    $fail('Phone number must be a valid Philippine mobile number (e.g. 09XXXXXXXXX or +639XXXXXXXXX).');
                }
            }],
            'recipient_name'   => ['nullable', 'string', 'max:150'],
            'message'          => ['required', 'string', 'min:5', 'max:160'],
        ]);

        $log = $this->sms->send(
            number:        $validated['recipient_number'],
            message:       $validated['message'],
            recipientName: $validated['recipient_name'] ?? null,
            event:         'manual',
        );

        $msg = match ($log->status) {
            'sent'    => 'SMS sent successfully.',
            'skipped' => 'SMS not sent: ' . ($log->error_message ?? 'SMS is turned off.'),
            default   => 'SMS failed to deliver: ' . ($log->error_message ?? 'Unknown error.'),
        };

        $flashType = $log->status === 'sent' ? 'success' : 'warning';

        return redirect()->route('sms.index')->with($flashType, $msg);
    }
}
