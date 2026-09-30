<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\PatientRepository;
use App\Services\AuditLogService;
use App\Services\PatientService;
use App\Support\AcademicLists;
use App\Support\DisplayFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly PatientService             $service,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX                                                               */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-patients');

        $filters  = array_filter($request->only(PatientRepository::FILTERS), fn ($v) => $v !== null && $v !== '');
        $patients = $this->patients->paginate($filters, DisplayFormat::perPage(20));

        return view('patients.index', [
            'patients'       => $patients,
            'filters'        => $filters,
            'categoryLabels' => Patient::categoryLabels(),
            'academic'       => AcademicLists::clientConfig(),
            'filterLists'    => AcademicLists::forCategory($filters['category'] ?? null),
            'stats'          => $this->summary(),
        ]);
    }

    /** Summary numbers for the patient list stat cards (whole register, not the filtered page). */
    private function summary(): array
    {
        $active   = Patient::where('is_active', true)->count();
        $inactive = Patient::where('is_active', false)->count();

        return [
            'total'      => $active + $inactive,
            'active'     => $active,
            'inactive'   => $inactive,
            'archived'   => Patient::onlyTrashed()->count(),
            'new_month'  => Patient::where('created_at', '>=', now()->startOfMonth())->count(),
            'categories' => Patient::query()->selectRaw('category, count(*) as total')->groupBy('category')
                ->orderByDesc('total')->pluck('total', 'category')->all(),
            'sexes'      => Patient::query()->selectRaw('sex, count(*) as total')->groupBy('sex')
                ->pluck('total', 'sex')->all(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE / STORE                                                      */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $this->authorize('create-patients');

        // "Create patient" from an unlinked online appointment request: prefill
        // what the requester typed and link the request once the patient is saved.
        $linkAppointment = null;
        $prefill         = [];
        if ($request->filled('from_appointment') && $request->user()->can('update-appointments')) {
            $linkAppointment = Appointment::whereNull('patient_id')->find($request->integer('from_appointment'));
            if ($linkAppointment) {
                $prefill = $this->prefillFromRequest($linkAppointment);
            }
        }

        return view('patients.create', [
            'patient'         => new Patient($prefill),
            'categoryLabels'  => Patient::categoryLabels(),
            'bloodTypes'      => Patient::bloodTypes(),
            'academic'        => AcademicLists::clientConfig(),
            'linkAppointment' => $linkAppointment,
        ]);
    }

    public function store(StorePatientRequest $request)
    {
        // CRITICAL-6 FIX: Wrap the entire create+generatePatientNumber in a single
        // DB transaction so the number generation lock and the INSERT are atomic.
        $linked  = null;
        $patient = DB::transaction(function () use ($request, &$linked) {
            $data = $request->safe()->except('link_appointment_id');
            $data['patient_number'] = $this->service->generatePatientNumber();
            $data['created_by']     = auth()->id();

            $patient = $this->patients->create($data);

            if ($request->filled('link_appointment_id') && $request->user()->can('update-appointments')) {
                $linked = Appointment::whereNull('patient_id')
                    ->lockForUpdate()
                    ->find($request->integer('link_appointment_id'));
                $linked?->update(['patient_id' => $patient->id]);
            }

            return $patient;
        });

        if ($linked) {
            AuditLogService::log(
                action: 'updated',
                module: 'appointments',
                description: "Linked online appointment request #{$linked->id} to new patient {$patient->full_name} ({$patient->patient_number})",
            );

            return redirect()
                ->route('appointments.show', $linked)
                ->with('success', "Patient {$patient->full_name} ({$patient->patient_number}) created and linked to this request. You can now approve it.");
        }

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', "Patient {$patient->full_name} ({$patient->patient_number}) created successfully.");
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW                                                                */
    /* ------------------------------------------------------------------ */
    public function show(Patient $patient)
    {
        $this->authorize('view-patients');

        $history = $this->service->getHealthHistory($patient);

        return view('patients.show', [
            'patient' => $patient,
            'history' => $history,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT / UPDATE                                                       */
    /* ------------------------------------------------------------------ */
    public function edit(Patient $patient)
    {
        $this->authorize('update-patients');

        return view('patients.edit', [
            'patient'        => $patient,
            'categoryLabels' => Patient::categoryLabels(),
            'bloodTypes'     => Patient::bloodTypes(),
            'academic'       => AcademicLists::clientConfig(),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $data               = $request->validated();
        $data['updated_by'] = auth()->id();

        $this->patients->update($patient, $data);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', "Patient {$patient->full_name} updated successfully.");
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY                                                             */
    /* ------------------------------------------------------------------ */
    public function destroy(Patient $patient)
    {
        $this->authorize('delete-patients');

        $name = $patient->full_name;
        $this->patients->delete($patient);

        return redirect()
            ->route('patients.index')
            ->with('success', "Patient {$name} has been deleted.");
    }

    /* ------------------------------------------------------------------ */
    /*  HISTORY (full visit history, printable)                             */
    /* ------------------------------------------------------------------ */
    public function history(Patient $patient)
    {
        $this->authorize('view-patients');

        $patient->load([
            'patientLogs'       => fn ($q) => $q->with('loggedBy')->orderByDesc('log_date')->orderByDesc('time_in'),
            'consultations'     => fn ($q) => $q->with('nurse')->orderByDesc('visit_date'),
            'dispensingRecords' => fn ($q) => $q->with('medicine', 'dispensedBy')->orderByDesc('dispensed_at'),
            'appointments'      => fn ($q) => $q->orderByDesc('appointment_date'),
        ]);

        // One chronological timeline across the four record types.
        $timeline = collect()
            ->merge($patient->patientLogs->map(fn ($l) => [
                'type' => 'Clinic visit', 'icon' => 'bi-journal-medical',
                'at'   => trim(($l->log_date?->format('Y-m-d') ?? '').' '.$l->time_in),
                'date' => $l->log_date, 'time' => $l->time_in,
                'title' => $this->visitTitle($l),
                'detail' => collect([$l->treatment ? 'Treatment: '.$l->treatment : null, 'Disposition: '.$l->disposition_label])->filter()->implode('. '),
                'by' => $l->loggedBy?->name,
                'url' => route('patient-logs.show', $l),
            ]))
            ->merge($patient->consultations->map(fn ($c) => [
                'type' => 'Consultation', 'icon' => 'bi-clipboard2-pulse',
                'at'   => trim(\Carbon\Carbon::parse($c->visit_date)->format('Y-m-d').' '.$c->visit_time),
                'date' => \Carbon\Carbon::parse($c->visit_date), 'time' => $c->visit_time,
                'title' => (string) $c->chief_complaint,
                'detail' => collect([$c->diagnosis ? 'Diagnosis: '.$c->diagnosis : null, $c->treatment ? 'Treatment: '.$c->treatment : null])->filter()->implode('. '),
                'by' => $c->nurse?->name,
                'url' => route('consultations.show', $c),
            ]))
            ->merge($patient->dispensingRecords->map(fn ($d) => [
                'type' => 'Medicine given', 'icon' => 'bi-capsule',
                'at'   => \Carbon\Carbon::parse($d->dispensed_at)->format('Y-m-d H:i:s'),
                'date' => \Carbon\Carbon::parse($d->dispensed_at), 'time' => \Carbon\Carbon::parse($d->dispensed_at)->format('H:i:s'),
                'title' => ($d->medicine?->name ?? 'Deleted medicine').' x '.$d->quantity.($d->medicine?->unit ? ' '.$d->medicine->unit : ''),
                'detail' => (string) $d->remarks,
                'by' => $d->dispensedBy?->name,
                'url' => route('dispensing.show', $d),
            ]))
            ->merge($patient->appointments->map(fn ($a) => [
                'type' => 'Appointment', 'icon' => 'bi-calendar-check',
                'at'   => $a->appointment_date->format('Y-m-d').' '.$a->appointment_time,
                'date' => $a->appointment_date, 'time' => $a->appointment_time,
                'title' => (string) $a->purpose,
                'detail' => 'Status: '.(Appointment::statusLabels()[$a->status] ?? $a->status).($a->provider ? '. With: '.$a->provider : ''),
                'by' => null,
                'url' => route('appointments.show', $a),
            ]))
            ->sortByDesc('at')
            ->values();

        return view('patients.history', compact('patient', 'timeline'));
    }

    /* ------------------------------------------------------------------ */
    /*  RESTORE  (MED-7 FIX)                                               */
    /* ------------------------------------------------------------------ */
    /**
     * Restore a soft-deleted patient record.
     * Route uses ->withTrashed() so the model binding resolves deleted records.
     * Gated on the restore-patients permission.
     */
    public function restore(Patient $patient)
    {
        $this->authorize('restore-patients');

        $patient->restore();

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', "Patient {$patient->full_name} has been restored.");
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */
    private function visitTitle($log): string
    {
        $sev = (string) $log->severity;

        return $log->complaint_summary.($sev !== '' ? " ({$sev})" : '');
    }

    /** Split the requester's name into first / last for the new patient form. */
    private function prefillFromRequest(Appointment $appointment): array
    {
        $name  = trim(preg_replace('/\s+/', ' ', (string) $appointment->requester_name));
        $parts = $name === '' ? [] : explode(' ', $name);
        $last  = count($parts) > 1 ? array_pop($parts) : '';

        return array_filter([
            'first_name'     => implode(' ', $parts),
            'last_name'      => $last,
            'contact_number' => $appointment->requester_contact,
            'email'          => $appointment->requester_email,
            'student_id'     => $appointment->requester_student_id,
        ]);
    }
}
