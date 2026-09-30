<?php

namespace App\Http\Controllers;

use App\Http\Requests\Consultation\StoreConsultationRequest;
use App\Http\Requests\Consultation\UpdateConsultationRequest;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use App\Services\ConsultationService;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    public function __construct(private readonly ConsultationService $service) {}

    /* ------------------------------------------------------------------ */
    /*  INDEX                                                               */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $this->authorize('view-consultations');

        $search  = trim((string) $request->get('search', ''));
        $date    = $request->get('date', '');
        $nurseId = $request->get('nurse_id', '');

        // Search grouped in one where() so it can never bypass the other filters;
        // matches patient name / number or the chief complaint / diagnosis.
        $consultations = Consultation::with('patient', 'nurse')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('patient', fn ($p) => $p->where(fn ($p2) => $p2
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name',  'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")))
                ->orWhere('chief_complaint', 'like', "%{$search}%")
                ->orWhere('diagnosis', 'like', "%{$search}%")
            ))
            ->when($date,    fn ($q) => $q->whereDate('visit_date', $date))
            ->when($nurseId, fn ($q) => $q->where('nurse_id', $nurseId))
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->paginate(20)
            ->withQueryString();

        $nurses = User::whereHas('consultations')->orderBy('name')->get();

        return view('consultations.index', [
            'consultations' => $consultations,
            'nurses'        => $nurses,
            'filters'       => compact('search', 'date', 'nurseId'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE / STORE                                                      */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $this->authorize('create-consultations');

        $patients = Patient::active()
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'patient_number']);

        $appointments = Appointment::whereIn('status', ['pending', 'approved'])
            ->orderByDesc('appointment_date')
            ->get(['id', 'patient_id', 'appointment_date', 'appointment_time', 'purpose']);

        // Pre-formatted on the server: the date cast serialises as an ISO
        // timestamp, which the old JS turned into "Invalid Date".
        $appointmentOptions = $appointments
            ->map(fn (Appointment $a) => [
                'id'         => $a->id,
                'patient_id' => $a->patient_id,
                'label'      => $a->appointment_date->format('M d, Y')
                    . ($a->appointment_time ? ' ' . \Carbon\Carbon::parse($a->appointment_time)->format('h:i A') : '')
                    . ' (' . ($a->purpose ?: 'No purpose listed') . ')',
            ])
            ->groupBy('patient_id');

        // "Start Consultation" from an appointment passes ?appointment_id=…
        $selectedAppointment = $request->integer('appointment_id') ?: null;
        $selectedPatient     = $request->integer('patient_id') ?: null;
        if ($selectedAppointment && ($appt = $appointments->firstWhere('id', $selectedAppointment))) {
            $selectedPatient = $appt->patient_id;
        } else {
            $selectedAppointment = null;
        }

        return view('consultations.create', compact(
            'patients', 'appointments', 'appointmentOptions', 'selectedPatient', 'selectedAppointment'
        ));
    }

    public function store(StoreConsultationRequest $request)
    {
        $data             = $request->validated();
        $data['nurse_id'] = auth()->id();

        $consultation = $this->service->create($data);

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Consultation record created successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  SHOW                                                                */
    /* ------------------------------------------------------------------ */
    public function show(Consultation $consultation)
    {
        $this->authorize('view-consultations');

        $consultation->load('patient', 'nurse', 'appointment.patient', 'dispensingRecords.medicine');

        return view('consultations.show', compact('consultation'));
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT / UPDATE                                                       */
    /* ------------------------------------------------------------------ */
    public function edit(Consultation $consultation)
    {
        $this->authorize('update-consultations');

        $patients = Patient::active()
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'patient_number']);

        $appointments = Appointment::where('patient_id', $consultation->patient_id)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->orderByDesc('appointment_date')
            ->get(['id', 'patient_id', 'appointment_date', 'appointment_time', 'purpose', 'status']);

        return view('consultations.edit', compact('consultation', 'patients', 'appointments'));
    }

    public function update(UpdateConsultationRequest $request, Consultation $consultation)
    {
        $this->authorize('update-consultations');

        $this->service->update($consultation, $request->validated());

        return redirect()
            ->route('consultations.show', $consultation)
            ->with('success', 'Consultation record updated successfully.');
    }

    /* ------------------------------------------------------------------ */
    /*  DESTROY                                                             */
    /* ------------------------------------------------------------------ */
    public function destroy(Consultation $consultation)
    {
        $this->authorize('delete-consultations');

        $consultation->delete();

        return redirect()
            ->route('consultations.index')
            ->with('success', 'Consultation record deleted.');
    }
}
