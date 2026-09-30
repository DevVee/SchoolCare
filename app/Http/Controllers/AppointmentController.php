<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidStatusTransition;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentTimeSlot;
use App\Models\Patient;
use App\Models\SpecialistVisit;
use App\Services\AppointmentBooking;
use App\Services\AppointmentService;
use App\Services\AuditLogService;
use App\Support\DisplayFormat;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $service,
        private readonly AppointmentBooking $booking,
    ) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX                                                               */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-appointments');

        $status   = (string) $request->get('status', '');
        $date     = (string) $request->get('date', '');
        $search   = (string) $request->get('search', '');
        $source   = in_array($request->get('source'), [Appointment::SOURCE_ONLINE, Appointment::SOURCE_STAFF, 'unlinked'], true)
            ? (string) $request->get('source') : '';
        $provider = (string) $request->get('provider', '');

        $appointments = Appointment::with('patient', 'createdBy', 'approvedBy', 'specialistVisit')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($date,   fn ($q) => $q->whereDate('appointment_date', $date))
            ->when($source === 'unlinked', fn ($q) => $q->whereNull('patient_id'))
            ->when(in_array($source, [Appointment::SOURCE_ONLINE, Appointment::SOURCE_STAFF], true), fn ($q) => $q->where('source', $source))
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('patient', function ($q) use ($search) {
                    $q->where('first_name',     'like', "%{$search}%")
                      ->orWhere('last_name',     'like', "%{$search}%")
                      ->orWhere('patient_number','like', "%{$search}%");
                })->orWhere('requester_name', 'like', "%{$search}%");
            }))
            ->orderByDesc('appointment_date')
            ->orderBy('appointment_time')
            ->paginate(DisplayFormat::perPage(20))
            ->withQueryString();

        $counts = Appointment::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('appointments.index', [
            'appointments'   => $appointments,
            'statusLabels'   => Appointment::statusLabels(),
            'counts'         => $counts,
            'filters'        => compact('status', 'date', 'search', 'source', 'provider'),
            'providers'      => settings()->list('appointment_providers'),
            'unlinkedCount'  => Appointment::whereNull('patient_id')->whereIn('status', ['pending', 'approved'])->count(),
            'reasonRequired' => (bool) settings('appointment_cancel_reason_required', true),
            'todayCounts'    => Appointment::whereDate('appointment_date', today())
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'onlinePending'  => Appointment::where('source', Appointment::SOURCE_ONLINE)->where('status', 'pending')->count(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE / STORE                                                      */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $this->authorize('create-appointments');

        $patients  = Patient::active()->orderBy('last_name')->orderBy('first_name')->get();
        $timeSlots = AppointmentTimeSlot::active()->get();
        $selected  = $request->integer('patient_id') ?: null;

        return view('appointments.create', [
            'patients'         => $patients,
            'timeSlots'        => $timeSlots,
            'selected'         => $selected,
            'providers'        => settings()->list('appointment_providers'),
            'specialistVisits' => $this->upcomingVisits(),
            'presetDate'       => $request->query('date'),
            'presetVisit'      => $request->integer('specialist_visit_id') ?: null,
        ]);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();

        $data['created_by'] = auth()->id();
        $data['status']     = 'pending';
        $data['source']     = Appointment::SOURCE_STAFF;
        $data               = $this->withVisitProvider($data);

        // Capacity is re-checked inside the insert transaction (race-safe).
        $appointment = $this->booking->book($data);

        app(\App\Services\AppointmentNotifier::class)->notify('created', $appointment);

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Appointment booked successfully and is pending approval.');
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW                                                                */
    /* ------------------------------------------------------------------ */
    public function show(Appointment $appointment)
    {
        $this->authorize('view-appointments');
        $appointment->load('patient', 'approvedBy', 'createdBy', 'consultation', 'specialistVisit');

        return view('appointments.show', [
            'appointment'    => $appointment,
            'matches'        => $appointment->needsPatientLink() ? $this->possibleMatches($appointment) : collect(),
            'reasonRequired' => (bool) settings('appointment_cancel_reason_required', true),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT / UPDATE                                                       */
    /* ------------------------------------------------------------------ */
    public function edit(Appointment $appointment)
    {
        $this->authorize('update-appointments');

        if (! $appointment->isEditable()) {
            return $this->notEditable($appointment);
        }

        $patients  = Patient::active()->orderBy('last_name')->orderBy('first_name')->get();
        $timeSlots = AppointmentTimeSlot::active()->get();

        // Keep the current patient selectable even if inactive / archived.
        if ($appointment->patient && ! $patients->contains('id', $appointment->patient_id)) {
            $patients->prepend($appointment->patient);
        }

        return view('appointments.edit', [
            'appointment'      => $appointment,
            'patients'         => $patients,
            'timeSlots'        => $timeSlots,
            'providers'        => settings()->list('appointment_providers'),
            'specialistVisits' => $this->upcomingVisits($appointment->specialist_visit_id),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $this->authorize('update-appointments');

        if (! $appointment->isEditable()) {
            return $this->notEditable($appointment);
        }

        $data = $this->withVisitProvider($request->validated());

        $this->booking->reschedule($appointment, $data);

        if (\App\Services\AppointmentNotifier::wasRescheduled($appointment)) {
            app(\App\Services\AppointmentNotifier::class)->notify('rescheduled', $appointment);
        }

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('success', 'Appointment updated successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  LINK AN ONLINE REQUEST TO A PATIENT                                 */
    /* ------------------------------------------------------------------ */
    public function linkPatient(Request $request, Appointment $appointment)
    {
        $this->authorize('update-appointments');

        $data = $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
        ], ['patient_id.exists' => 'Selected patient not found or archived.']);

        if ($appointment->isTerminal()) {
            return back()->with('error', 'This appointment is closed and can no longer be linked.');
        }

        $patient = Patient::findOrFail($data['patient_id']);
        $appointment->update(['patient_id' => $patient->id]);

        AuditLogService::log(
            action: 'updated',
            module: 'appointments',
            description: "Linked appointment #{$appointment->id} (requested by {$appointment->requester_name}) to patient {$patient->full_name} ({$patient->patient_number})",
        );

        return redirect()->route('appointments.show', $appointment)
            ->with('success', "Linked to {$patient->full_name}. The request can now be approved.");
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY                                                             */
    /* ------------------------------------------------------------------ */
    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete-appointments');
        $appointment->delete();

        return redirect()
            ->route('appointments.index')
            ->with('success', 'Appointment deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  STATUS TRANSITIONS                                                  */
    /* ------------------------------------------------------------------ */
    public function approve(Request $request, Appointment $appointment)
    {
        $this->authorize('approve-appointments');

        if ($appointment->needsPatientLink()) {
            return back()->with('error', 'Link this online request to a patient record (or create one) before approving it.');
        }

        try {
            $this->service->approve($appointment);
        } catch (InvalidStatusTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment approved successfully.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        // CRITICAL-8 FIX: Use the correct 'cancel-appointments' permission.
        // Previously used 'approve-appointments', causing a mismatch where nurses
        // could POST cancel but the Blade @can('cancel', $appointment) hid the button.
        $this->authorize('cancel-appointments');

        // Admin → Settings → Appointments → "Require a reason when cancelling".
        $required = (bool) settings('appointment_cancel_reason_required', true);

        $request->validate([
            'cancelled_reason' => [$required ? 'required' : 'nullable', 'string', 'max:500'],
        ], ['cancelled_reason.required' => 'Please give a reason for cancelling.']);

        try {
            $this->service->cancel($appointment, trim((string) $request->input('cancelled_reason', '')));
        } catch (InvalidStatusTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment cancelled.');
    }

    public function noShow(Appointment $appointment)
    {
        $this->authorize('complete-appointments');
        try {
            $this->service->markNoShow($appointment);
        } catch (InvalidStatusTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment marked as No Show.');
    }

    public function complete(Appointment $appointment)
    {
        $this->authorize('complete-appointments');

        if ($appointment->needsPatientLink()) {
            return back()->with('error', 'Link this appointment to a patient record first.');
        }

        try {
            $this->service->markCompleted($appointment);
        } catch (InvalidStatusTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment marked as Completed.');
    }

    /** Remaining places per slot for a date (used by the booking form). */
    public function availability(Request $request)
    {
        $this->authorize('view-appointments');

        $request->validate(['date' => ['required', 'date']]);

        return response()->json([
            'date'  => $request->query('date'),
            'slots' => $this->booking->slotsForDate((string) $request->query('date'))->map(fn ($s) => [
                'time'      => $s['slot']->slot_time,
                'label'     => $s['slot']->display_label,
                'remaining' => $s['remaining'],
                'available' => $s['available'],
            ])->values(),
            'visits' => SpecialistVisit::scheduled()->whereDate('visit_date', $request->query('date'))->get()
                ->map(fn ($v) => ['id' => $v->id, 'label' => "{$v->type}: {$v->specialist_name} ({$v->time_range})"])->values(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** Completed / cancelled / no-show appointments are read-only. */
    private function notEditable(Appointment $appointment)
    {
        $status = Appointment::statusLabels()[$appointment->status] ?? $appointment->status;

        return redirect()
            ->route('appointments.show', $appointment)
            ->with('error', "This appointment is {$status} and can no longer be edited.");
    }

    /** A specialist visit implies its provider when none was chosen. */
    private function withVisitProvider(array $data): array
    {
        if (! empty($data['specialist_visit_id']) && empty($data['provider'])) {
            $data['provider'] = SpecialistVisit::whereKey($data['specialist_visit_id'])->value('type');
        }

        return $data;
    }

    private function upcomingVisits(?int $keepId = null)
    {
        return SpecialistVisit::query()
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereDate('visit_date', '>=', today())->where('status', 'scheduled'))
                ->when($keepId, fn ($w) => $w->orWhere('id', $keepId)))
            ->orderBy('visit_date')->orderBy('start_time')
            ->limit(100)
            ->get();
    }

    /** Patients that look like the requester of an unlinked online request. */
    private function possibleMatches(Appointment $appointment)
    {
        $sid     = trim((string) $appointment->requester_student_id);
        $contact = preg_replace('/\D/', '', (string) $appointment->requester_contact);
        $tail    = strlen($contact) >= 10 ? substr($contact, -10) : null;
        $words   = array_values(array_filter(preg_split('/\s+/', trim((string) $appointment->requester_name))));

        return Patient::query()
            ->where(function ($q) use ($sid, $tail, $words) {
                if ($sid !== '') {
                    $q->orWhere('student_id', $sid);
                }
                if ($tail) {
                    $q->orWhere('contact_number', 'like', "%{$tail}")->orWhere('guardian_contact', 'like', "%{$tail}");
                }
                if (count($words) >= 2) {
                    $q->orWhere(fn ($w) => $w->where('first_name', 'like', $words[0].'%')->where('last_name', 'like', end($words).'%'));
                }
                if ($sid === '' && ! $tail && count($words) < 2) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get();
    }
}
