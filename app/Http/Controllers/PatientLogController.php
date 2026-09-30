<?php

namespace App\Http\Controllers;

use App\Exceptions\StockException;
use App\Http\Requests\PatientLog\SavePatientLogRequest;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Services\PatientLogService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientLogController extends Controller
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly PatientLogService $logs,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX: Clinic Logbook                                              */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-patient-logs');

        // Date range. The legacy single ?date= still works (from = to = date).
        $single   = $this->validDate($request->input('date'));
        $dateFrom = $this->validDate($request->input('date_from')) ?? $single;
        $dateTo   = $this->validDate($request->input('date_to')) ?? $single;
        if (! $dateFrom && ! $dateTo) {
            $dateFrom = $dateTo = today()->toDateString();
        }
        $dateFrom ??= $dateTo;
        $dateTo   ??= $dateFrom;
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $search      = trim((string) $request->input('search', ''));
        $disposition = (string) $request->input('disposition', '');
        $severity    = (string) $request->input('severity', '');
        $reason      = (string) $request->input('reason', '');
        $category    = (string) $request->input('category', '');
        $status      = (string) $request->input('status', '');

        if (! array_key_exists($disposition, PatientLog::dispositions())) {
            $disposition = '';
        }
        if (! in_array($severity, PatientLog::severities(), true)) {
            $severity = '';
        }
        if (! in_array($reason, PatientLog::reasonOptions(), true)) {
            $reason = '';
        }
        if (! array_key_exists($category, Patient::categoryLabels())) {
            $category = '';
        }
        if (! in_array($status, ['in_clinic', 'discharged'], true)) {
            $status = '';
        }

        $query = PatientLog::with(['patient', 'loggedBy', 'dispensingRecords.medicine'])
            ->withCount('attachments')
            ->whereDate('log_date', '>=', $dateFrom)
            ->whereDate('log_date', '<=', $dateTo)
            ->when($disposition, fn ($q) => $q->where('disposition', $disposition))
            ->when($severity, fn ($q) => $q->where('severity', $severity))
            ->when($reason, fn ($q) => $q->whereJsonContains('reasons', $reason))
            ->when($category, fn ($q) => $q->whereHas('patient', fn ($p) => $p->withTrashed()->where('category', $category)))
            ->when($status === 'in_clinic', fn ($q) => $q->whereNull('time_out'))
            ->when($status === 'discharged', fn ($q) => $q->whereNotNull('time_out'));

        if ($search !== '') {
            // Grouped so the OR never bypasses the other filters.
            $query->where(fn ($w) => $w
                ->whereHas('patient', fn ($q) => $q->withTrashed()->where(fn ($q2) => $q2
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")))
                ->orWhere('chief_complaint', 'like', "%{$search}%")
                ->orWhere('other_reason', 'like', "%{$search}%")
                ->orWhere('reasons', 'like', '%'.trim(json_encode($search), '"').'%')
            );
        }

        $logs = $query->orderByDesc('log_date')->orderByDesc('time_in')->paginate(25)->withQueryString();

        $stats = [
            'today' => PatientLog::today()->count(),
            'week'  => PatientLog::whereDate('log_date', '>=', today()->startOfWeek()->toDateString())
                ->whereDate('log_date', '<=', today()->endOfWeek()->toDateString())
                ->count(),
            'month' => PatientLog::whereMonth('log_date', today()->month)
                ->whereYear('log_date', today()->year)
                ->count(),
        ];

        $inClinic = PatientLog::inClinic()->with('patient')->orderBy('time_in')->get();

        $filters = compact('dateFrom', 'dateTo', 'search', 'disposition', 'severity', 'reason', 'category', 'status');

        return view('patient-logs.index', compact('logs', 'filters', 'stats', 'inClinic') + [
            // Kept for older partials that still read these.
            'date'        => $dateFrom === $dateTo ? $dateFrom : '',
            'search'      => $search,
            'disposition' => $disposition,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE / STORE                                                      */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $this->authorize('create-patient-logs');

        return view('patient-logs.create', [
            'patients'        => $this->patientOptions(),
            'medicines'       => $this->medicineOptions(),
            'selectedPatient' => $request->input('patient_id'),
        ]);
    }

    public function store(SavePatientLogRequest $request)
    {
        $attributes = $request->logAttributes() + [
            'logged_by'    => auth()->id(),
            'sms_guardian' => $request->boolean('sms_guardian'),
            'sms_sent'     => false,
        ];

        try {
            $log = $this->logs->create($attributes, $request->medicineRows());
        } catch (StockException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        $given = $log->dispensingRecords()->count();
        $msg   = "Patient log for {$log->patient->full_name} saved."
            .($given ? " {$given} ".str('medicine')->plural($given).' deducted from stock.' : '');

        // Optionally notify guardian (queued; outcome in the SMS log).
        if ($request->boolean('sms_guardian')) {
            $smsLog = $this->sms->sendClinicLogNotice($log);
            $msg .= match ($smsLog?->status) {
                'sent'    => ' SMS sent to guardian.',
                'pending' => ' SMS to guardian queued.',
                'skipped', 'failed' => ' Guardian SMS not sent: '.$smsLog->error_message,
                default   => '',
            };
        }

        return redirect()->route('patient-logs.show', $log)->with('success', $msg);
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW                                                                */
    /* ------------------------------------------------------------------ */
    public function show(PatientLog $patientLog)
    {
        $this->authorize('view-patient-logs');

        $patientLog->load([
            'patient', 'loggedBy',
            'dispensingRecords.medicine', 'dispensingRecords.dispensedBy', 'dispensingRecords.transactions.batch',
            'attachments.uploadedBy',
        ]);

        return view('patient-logs.show', ['log' => $patientLog]);
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT / UPDATE                                                       */
    /* ------------------------------------------------------------------ */
    public function edit(PatientLog $patientLog)
    {
        $this->authorize('update-patient-logs');

        $patientLog->load(['patient', 'dispensingRecords.medicine']);

        $patients = $this->patientOptions();

        // Keep the current patient selectable even if inactive / archived.
        if (! $patients->contains('id', $patientLog->patient_id) && $patientLog->patient) {
            $patients->prepend($patientLog->patient);
        }

        return view('patient-logs.edit', [
            'patientLog' => $patientLog,
            'patients'   => $patients,
            'medicines'  => $this->medicineOptions(),
        ]);
    }

    public function update(SavePatientLogRequest $request, PatientLog $patientLog)
    {
        try {
            $this->logs->update($patientLog, $request->logAttributes(), $request->medicineRows());
        } catch (StockException $e) {
            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()
            ->route('patient-logs.show', $patientLog)
            ->with('success', 'Log entry updated.');
    }

    /* ------------------------------------------------------------------ */
    /*  DISCHARGE: patient leaves the clinic                               */
    /* ------------------------------------------------------------------ */
    public function discharge(Request $request, PatientLog $patientLog)
    {
        $this->authorize('update-patient-logs');

        $data = $request->validate([
            'disposition'     => ['nullable', Rule::in(array_keys(PatientLog::dispositions()))],
            'notify_guardian' => ['nullable', 'boolean'],
        ]);

        try {
            $smsLog = $this->logs->discharge(
                $patientLog,
                $data['disposition'] ?? null,
                $request->boolean('notify_guardian', true),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $name = $patientLog->patient?->full_name ?? 'Patient';
        $msg  = "{$name} discharged at ".Carbon::parse($patientLog->time_out)->format('h:i A').'.';
        $msg .= match ($smsLog?->status) {
            'sent'    => ' Guardian notified by SMS.',
            'pending' => ' Guardian SMS queued.',
            'failed'  => ' Guardian SMS not sent: '.$smsLog->error_message,
            default   => '',
        };

        return back()->with('success', $msg);
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY                                                             */
    /* ------------------------------------------------------------------ */
    public function destroy(PatientLog $patientLog)
    {
        $this->authorize('delete-patient-logs');
        $patientLog->delete();

        return redirect()->route('patient-logs.index')->with('success', 'Log entry removed.');
    }

    /* ------------------------------------------------------------------ */

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function patientOptions()
    {
        return Patient::active()
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'middle_name', 'patient_number',
                   'guardian_name', 'guardian_contact', 'category',
                   'section', 'year_level', 'program_strand', 'deleted_at']);
    }

    /** Medicines that can be given now, with usable stock and next expiry (FEFO). */
    private function medicineOptions()
    {
        return Medicine::dispensable()
            ->withSum(['batches as usable_quantity' => fn ($q) => $q->usable()], 'quantity')
            ->withMin(['batches as next_expiry' => fn ($q) => $q->usable()], 'expiry_date')
            ->orderBy('name')
            ->get(['id', 'name', 'generic_name', 'unit', 'quantity', 'expiration_date']);
    }
}
