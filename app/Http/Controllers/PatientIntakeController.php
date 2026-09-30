<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\PatientRules;
use App\Models\Patient;
use App\Models\PatientIntakeSubmission;
use App\Services\AuditLogService;
use App\Services\PatientService;
use App\Services\Patients\PatientColumns;
use App\Services\SmsService;
use App\Support\AcademicLists;
use App\Support\DisplayFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff review queue for the public health information form.
 * Approve as a new patient, approve into an existing patient (field by field),
 * or reject with a note. Requires review-intake.
 */
class PatientIntakeController extends Controller
{
    public function __construct(private readonly PatientService $patients) {}

    public function index(Request $request)
    {
        $this->authorize('review-intake');

        $status = array_key_exists((string) $request->query('status'), PatientIntakeSubmission::STATUSES)
            ? (string) $request->query('status') : 'pending';

        $submissions = PatientIntakeSubmission::query()
            ->with('reviewedBy', 'patient')
            ->where('status', $status)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = (string) $request->query('search');
                $q->where(fn ($w) => $w->where('last_name', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('student_id', 'like', "%{$s}%"));
            })
            ->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(DisplayFormat::perPage(20))
            ->withQueryString();

        return view('patients.intake.index', [
            'submissions'    => $submissions,
            'status'         => $status,
            'counts'         => PatientIntakeSubmission::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'categoryLabels' => Patient::categoryLabels(),
            'publicUrl'      => route('public.health-form.create'),
            'enabled'        => (bool) settings('public_intake_enabled', false),
        ]);
    }

    public function show(PatientIntakeSubmission $submission)
    {
        $this->authorize('review-intake');

        $matches = $submission->status === 'pending' ? $submission->possibleMatches() : collect();

        return view('patients.intake.show', [
            'submission'     => $submission,
            'matches'        => $matches,
            'fields'         => $this->fieldLabels(),
            'categoryLabels' => Patient::categoryLabels(),
            'sexLabels'      => Patient::sexLabels(),
        ]);
    }

    /** Approve as a new patient record. */
    public function approve(Request $request, PatientIntakeSubmission $submission)
    {
        $this->authorize('review-intake');
        $this->authorize('create-patients');
        $this->ensurePending($submission);

        $data = $this->validatePayload($submission->payload ?? []);

        $patient = DB::transaction(function () use ($submission, $data) {
            $locked = PatientIntakeSubmission::whereKey($submission->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'This submission was already reviewed.']);
            }

            $data['patient_number'] = $this->patients->generatePatientNumber();
            $data['created_by']     = auth()->id();
            $patient = Patient::create($data);

            $locked->update([
                'status'      => 'approved',
                'patient_id'  => $patient->id,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $patient;
        });

        AuditLogService::log(
            action: 'approved',
            module: 'patient-intake',
            description: "Approved online health form #{$submission->id} as new patient {$patient->full_name} ({$patient->patient_number})",
        );

        $this->acknowledge($submission->fresh(), $patient);

        return redirect()->route('patients.show', $patient)
            ->with('success', "Health form approved. Patient {$patient->full_name} ({$patient->patient_number}) was created.");
    }

    /** Approve into an existing patient: per field, keep the existing value or take the submitted one. */
    public function merge(Request $request, PatientIntakeSubmission $submission)
    {
        $this->authorize('review-intake');
        $this->authorize('update-patients');
        $this->ensurePending($submission);

        $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'take'       => ['nullable', 'array'],
            'take.*'     => ['string', Rule::in(array_keys($this->fieldLabels()))],
        ]);

        $patient = Patient::findOrFail($request->integer('patient_id'));
        $payload = $submission->payload ?? [];
        $take    = array_values(array_intersect((array) $request->input('take', []), array_keys($payload)));

        // The merged record must still be valid as a whole.
        $merged = array_merge($patient->only(PatientRules::fields()), array_intersect_key($payload, array_flip($take)));
        $merged['birthdate'] = $merged['birthdate'] instanceof \DateTimeInterface ? $merged['birthdate']->format('Y-m-d') : $merged['birthdate'];
        $validated = $this->validatePayload($merged, $patient);
        $changes   = array_intersect_key($validated, array_flip($take));

        DB::transaction(function () use ($submission, $patient, $changes) {
            $locked = PatientIntakeSubmission::whereKey($submission->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'This submission was already reviewed.']);
            }

            if ($changes) {
                $patient->update($changes + ['updated_by' => auth()->id()]);
            }

            $locked->update([
                'status'             => 'approved',
                'patient_id'         => $patient->id,
                'matched_patient_id' => $patient->id,
                'reviewed_by'        => auth()->id(),
                'reviewed_at'        => now(),
                'review_note'        => $changes ? 'Updated: '.implode(', ', array_keys($changes)) : 'No fields changed',
            ]);
        });

        AuditLogService::log(
            action: 'approved',
            module: 'patient-intake',
            description: "Approved online health form #{$submission->id} into patient {$patient->full_name} ({$patient->patient_number})"
                .($changes ? '. Updated fields: '.implode(', ', array_keys($changes)) : '. No fields changed'),
        );

        $this->acknowledge($submission->fresh(), $patient);

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Health form approved. '.($changes ? count($changes).' field(s) updated on the patient record.' : 'No fields were changed.'));
    }

    public function reject(Request $request, PatientIntakeSubmission $submission)
    {
        $this->authorize('review-intake');
        $this->ensurePending($submission);

        $data = $request->validate(['review_note' => ['required', 'string', 'max:1000']], [
            'review_note.required' => 'Please note why the form is rejected.',
        ]);

        $submission->update([
            'status'      => 'rejected',
            'review_note' => $data['review_note'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        AuditLogService::log(
            action: 'rejected',
            module: 'patient-intake',
            description: "Rejected online health form #{$submission->id} ({$submission->full_name}). Note: {$data['review_note']}",
        );

        return redirect()->route('patients.intake.index')->with('success', 'Health form rejected.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function ensurePending(PatientIntakeSubmission $submission): void
    {
        abort_if($submission->status !== 'pending', 409, 'This submission was already reviewed.');
    }

    /** Validate with the staff rules; errors are shown on the review page. */
    private function validatePayload(array $payload, ?Patient $current = null): array
    {
        $validator = validator(
            array_intersect_key($payload, array_flip(PatientRules::fields())),
            PatientRules::rules($current),
            PatientRules::messages()
        );
        $validator->after(PatientRules::academicCheck(fn (string $key) => $payload[$key] ?? null, $current));

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'payload' => 'The submitted data does not pass the patient form rules: '.implode(' ', $validator->errors()->all()),
            ]);
        }

        return $validator->validated();
    }

    /** Field => label, in form order (for the review table). */
    private function fieldLabels(): array
    {
        $labels = PatientColumns::COLUMNS;
        unset($labels['patient_number']);
        $labels['birthdate'] = 'Birthdate';

        return $labels;
    }

    /** Optional SMS acknowledgement (Settings → Notifications). Never throws. */
    private function acknowledge(PatientIntakeSubmission $submission, Patient $patient): void
    {
        try {
            $sms    = app(SmsService::class);
            $number = $sms->pickNumber($submission->payload['contact_number'] ?? null, $submission->payload['guardian_contact'] ?? null);
            $sms->notify('intake_approved', $number, [
                'name'      => $patient->first_name,
                'full_name' => $patient->full_name,
                'date'      => now()->format('F d, Y'),
            ], $submission, $patient->full_name);
        } catch (\Throwable $e) {
            Log::warning('Intake approval SMS failed', ['submission' => $submission->id, 'error' => $e->getMessage()]);
        }
    }
}
