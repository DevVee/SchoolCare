<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\SpecialistVisit;
use App\Notifications\OnlineAppointmentRequestNotification;
use App\Services\AppointmentBooking;
use App\Services\AppointmentNotifier;
use App\Services\AuditLogService;
use App\Services\OnlineBooking;
use App\Services\SmsService;
use App\Support\AcademicLists;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public appointment request (SSCMS appointments/web-new-appointment.php) and
 * the public "today's schedule" board (SSCMS appointments/index.php).
 *
 * Everything the form asks and offers is set in Admin > Settings >
 * Appointments (rules: App\Services\OnlineBooking). While "Accept online
 * appointment requests" is off, the request page shows the closed notice from
 * the same settings page, and the time lookup and schedule board are 404.
 *
 * Requests are stored as pending with source = online. They are linked to a
 * patient automatically only when BOTH the student ID and the contact number
 * match one patient; otherwise staff link them (or create the patient) before
 * approving.
 */
class PublicAppointmentController extends Controller
{
    /** Name of the hidden honeypot field (real people never fill it). */
    public const HONEYPOT = 'website';

    /** Grade / program / section: form field => [AcademicLists key, stored column, label]. */
    private const SCHOOL_FIELDS = [
        'year_level'     => ['levels',   'requester_year_level', 'grade or year level'],
        'program_strand' => ['programs', 'requester_program',    'program, strand or course'],
        'section'        => ['sections', 'requester_section',    'section'],
    ];

    public function __construct(
        private readonly AppointmentBooking $booking,
        private readonly OnlineBooking $online,
    ) {}

    public function create(Request $request)
    {
        if (! $this->online->enabled()) {
            return view('public.request-appointment-closed', [
                'message' => trim((string) settings('public_booking_closed_message', '')),
            ]);
        }

        $provider   = $this->online->shows('provider') ? (string) $request->old('provider', '') : '';
        $specialist = $this->online->isSpecialist($provider);
        $dates      = $this->online->dates();
        $date       = (string) $request->old('appointment_date', '');
        $listed     = collect($dates)->contains(fn ($d) => $d['date'] === $date && (! $specialist || in_array($provider, $d['visits'], true)));
        if (! $listed) {
            $date = $this->firstOpenDate($dates, $provider) ?? '';
        }

        return view('public.request-appointment', [
            'online'      => $this->online,
            'purposes'    => $this->online->purposes(),
            'providers'   => $this->online->providers(),
            'specialists' => $this->online->specialistProviders(),
            'categories'  => $this->online->categories(),
            'academic'    => AcademicLists::clientConfig(),
            'dates'       => $dates,
            'dateValue'   => $date,
            'slots'       => $date !== '' ? $this->online->slots($date, $provider ?: null) : collect(),
            'week'        => ClinicHours::week(),
            'lastDate'    => $this->online->lastDate(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->online->enabled()) {
            return redirect()->route('public.appointments.create');
        }

        // Honeypot: pretend it worked, store nothing.
        if (filled($request->input(self::HONEYPOT))) {
            Log::info('Public appointment request rejected by honeypot', ['ip' => $request->ip()]);

            return redirect()->route('public.appointments.thanks');
        }

        $validator = validator($request->all(), $this->rules($request), $this->messages());
        $validator->after(fn (Validator $v) => $this->checkSchoolDetails($v, $request));
        $validator->after(fn (Validator $v) => $this->checkDateAndTime($v, $request));
        $data = $validator->validate();

        $date     = Carbon::parse($data['appointment_date'])->startOfDay();
        $provider = $data['provider'] ?? null;
        $contact  = app(SmsService::class)->normalizeNumber($data['requester_contact']);
        $patient  = $this->strongMatch($data['requester_student_id'] ?? null, $contact);
        $purpose  = OnlineBooking::isOther($data['purpose']) && filled($data['purpose_other'] ?? null)
            ? trim($data['purpose_other'])
            : $data['purpose'];

        // A scheduled specialist day on that date for the chosen provider.
        $visitId = $provider
            ? SpecialistVisit::scheduled()->whereDate('visit_date', $date->toDateString())->where('type', $provider)->value('id')
            : null;

        $appointment = $this->booking->book([
            'patient_id'           => $patient?->id,
            'appointment_date'     => $date->toDateString(),
            'appointment_time'     => $this->booking->normalizeTime($data['appointment_time']),
            'purpose'              => $purpose,
            'provider'             => $provider,
            'specialist_visit_id'  => $visitId,
            'notes'                => $data['details'] ?? null,
            'status'               => 'pending',
            'source'               => Appointment::SOURCE_ONLINE,
            'requester_name'       => trim($data['requester_name']),
            'requester_contact'    => $data['requester_contact'],
            'requester_email'      => $data['requester_email'] ?? null,
            'requester_student_id' => $data['requester_student_id'] ?? null,
            'requester_category'   => $data['category'] ?? null,
            'requester_year_level' => $data['year_level'] ?? null,
            'requester_program'    => $data['program_strand'] ?? null,
            'requester_section'    => $data['section'] ?? null,
            'created_by'           => null,
        ], enforceClinicHours: true);

        AuditLogService::log(
            action: 'created',
            module: 'appointments',
            description: "Online appointment request #{$appointment->id} from {$appointment->requester_name} for "
                .$date->format('M d, Y').($patient ? " (matched patient {$patient->patient_number})" : ' (not linked to a patient)'),
        );

        // Acknowledgement SMS/email (same path as staff bookings; never throws).
        app(AppointmentNotifier::class)->notify('created', $appointment);
        $this->notifyClinic($appointment);

        return redirect()->route('public.appointments.thanks')->with('request_summary', [
            'date'     => $date->format('l, F d, Y'),
            'time'     => Carbon::parse($appointment->appointment_time)->format('h:i A'),
            'purpose'  => $purpose,
            'provider' => $provider,
        ]);
    }

    public function thanks()
    {
        if (! $this->online->enabled()) {
            return redirect()->route('public.appointments.create');
        }

        return view('public.request-appointment-thanks', [
            'summary' => session('request_summary'),
            'message' => trim((string) settings('public_booking_success_message', '')),
        ]);
    }

    /** Times for a date as the form offers them (public; no patient data). */
    public function slots(Request $request)
    {
        $this->ensureEnabled();

        $request->validate([
            'date'     => ['required', 'date_format:Y-m-d'],
            'provider' => ['nullable', 'string', 'max:60'],
        ]);
        $date     = Carbon::parse((string) $request->query('date'))->startOfDay();
        $provider = $this->online->shows('provider') ? ($request->query('provider') ?: null) : null;
        $problem  = $this->online->dateProblem($date, $provider);
        $slots    = $this->online->slots($date, $provider);

        return response()->json([
            'date'    => $date->toDateString(),
            'open'    => $problem === null,
            'message' => $problem ?? ($slots->contains('available', true) ? null : 'All times are taken on this date. Choose another date.'),
            'slots'   => $slots->map(fn (array $s) => [
                'time'      => $s['time'],
                'label'     => $s['label'],
                'remaining' => $s['remaining'],
                'available' => $s['available'],
                'state'     => $s['state'],
            ])->values(),
        ]);
    }

    /** Public board: today's slots with remaining capacity and today's specialist visits. */
    public function schedule()
    {
        $this->ensureEnabled();

        $today = today();

        return view('public.clinic-schedule', [
            'today'  => $today,
            'hours'  => ClinicHours::forDate($today),
            'week'   => ClinicHours::week(),
            'slots'  => ClinicHours::isOpenOn($today) ? $this->booking->slotsForDate($today->toDateString()) : collect(),
            'visits' => SpecialistVisit::scheduled()->whereDate('visit_date', $today->toDateString())->orderBy('start_time')->get(),
            'upcomingVisits' => SpecialistVisit::scheduled()
                ->whereDate('visit_date', '>', $today->toDateString())
                ->whereDate('visit_date', '<=', $today->copy()->addDays(14)->toDateString())
                ->orderBy('visit_date')->orderBy('start_time')
                ->limit(10)->get(),
        ]);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    private function rules(Request $request): array
    {
        $o        = $this->online;
        $purposes = $o->purposes();
        $need     = fn (string $field) => $o->requires($field) ? 'required' : 'nullable';

        $rules = [
            'requester_name'    => ['required', 'string', 'min:3', 'max:150'],
            'requester_contact' => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                if (! app(SmsService::class)->normalizeNumber((string) $value)) {
                    $fail('Enter a valid mobile number, for example 09171234567.');
                }
            }],
            'appointment_date'  => ['required', 'date_format:Y-m-d'],
            'appointment_time'  => ['required', 'string', 'max:8'],
            'purpose'           => $purposes ? ['required', 'string', Rule::in($purposes)] : ['required', 'string', 'max:150'],
            'purpose_other'     => [
                Rule::requiredIf(fn () => $purposes && OnlineBooking::isOther((string) $request->input('purpose'))),
                'nullable', 'string', 'max:150',
            ],
        ];

        if ($o->shows('student_id')) {
            $rules['requester_student_id'] = [$need('student_id'), 'string', 'max:50'];
        }
        if ($o->shows('email')) {
            $rules['requester_email'] = [$need('email'), 'email', 'max:150'];
        }
        if ($o->shows('category')) {
            $rules['category'] = [$need('category'), 'string', Rule::in(array_keys($o->categories()))];
        }
        if ($o->shows('school')) {
            foreach (array_keys(self::SCHOOL_FIELDS) as $field) {
                $rules[$field] = ['nullable', 'string', 'max:100'];
            }
        }
        if ($o->shows('provider')) {
            $rules['provider'] = [$need('provider'), 'string', Rule::in($o->providers())];
        }
        if ($o->shows('details')) {
            $rules['details'] = [$need('details'), 'string', 'max:500'];
        }
        if ($o->consentRequired()) {
            $rules['consent'] = ['accepted'];
        }

        return $rules;
    }

    private function messages(): array
    {
        return [
            'requester_name.required'       => 'Please enter your full name.',
            'requester_name.min'            => 'Please enter your full name.',
            'requester_contact.required'    => 'Please enter a mobile number so the clinic can reach you.',
            'requester_student_id.required' => 'Please enter your student or employee ID.',
            'requester_email.required'      => 'Please enter your email address.',
            'requester_email.email'         => 'Enter a valid email address'.($this->online->requires('email') ? '.' : ', or leave it empty.'),
            'category.required'             => 'Choose who the appointment is for.',
            'category.in'                   => 'Choose one of the categories in the list.',
            'appointment_date.required'     => 'Choose a date for your visit.',
            'appointment_date.date_format'  => 'Choose a date from the list.',
            'appointment_time.required'     => 'Choose a time.',
            'purpose.required'              => 'Choose the reason for your visit.',
            'purpose.in'                    => 'Choose one of the reasons in the list.',
            'purpose_other.required'        => 'Please type the reason for your visit.',
            'provider.required'             => 'Choose who you would like to see.',
            'provider.in'                   => 'Choose one of the options in the list.',
            'details.required'              => 'Please tell the nurse a little about your visit.',
            'consent.accepted'              => 'Please tick the box to agree to the privacy statement.',
        ];
    }

    /** Grade / program / section must fit the category's lists, and be filled when required. */
    private function checkSchoolDetails(Validator $v, Request $request): void
    {
        if (! $this->online->shows('school') || $v->errors()->has('category')) {
            return;
        }

        $category = $this->online->shows('category') ? (trim((string) $request->input('category')) ?: null) : null;
        $values   = [];
        foreach (array_keys(self::SCHOOL_FIELDS) as $field) {
            $values[$field] = trim((string) $request->input($field));
        }

        foreach (AcademicLists::errors($category, $values) as $attr => $message) {
            $v->errors()->add($attr, $message);
        }

        // Required: only the lists that apply to the chosen category (an empty list means "not applicable").
        if ($this->online->requires('school') && ($category !== null || ! $this->online->shows('category'))) {
            $lists = AcademicLists::forCategory($category);
            foreach (self::SCHOOL_FIELDS as $field => [$key, , $label]) {
                if (($lists[$key] ?? []) !== [] && $values[$field] === '' && ! $v->errors()->has($field)) {
                    $v->errors()->add($field, "Choose the {$label}.");
                }
            }
        }
    }

    /** The date must be offered, and the time open on it (the booking re-checks capacity). */
    private function checkDateAndTime(Validator $v, Request $request): void
    {
        if ($v->errors()->hasAny(['appointment_date', 'appointment_time', 'provider'])) {
            return;
        }

        $date     = Carbon::parse((string) $request->input('appointment_date'))->startOfDay();
        $provider = $this->online->shows('provider') ? ($request->input('provider') ?: null) : null;

        if ($problem = $this->online->dateProblem($date, $provider)) {
            $v->errors()->add('appointment_date', $problem);

            return;
        }
        if ($this->online->dayIsFull($date)) {
            $v->errors()->add('appointment_date', 'The clinic has no more places on '.$date->format('F j').'. Choose another date.');

            return;
        }

        $slot = $this->online->slot($date, (string) $request->input('appointment_time'), $provider);
        $hours = $this->online->minNoticeHours();

        $message = match ($slot['state'] ?? null) {
            null    => 'Choose one of the open times in the list.',
            'past'  => 'This time has already passed. Choose a later time.',
            'soon'  => 'Please request at least '.$hours.' '.($hours === 1 ? 'hour' : 'hours').' ahead. Choose a later time.',
            'full'  => 'This time is fully booked. Choose another time.',
            default => null,
        };
        if ($message) {
            $v->errors()->add('appointment_time', $message);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function ensureEnabled(): void
    {
        abort_unless($this->online->enabled(), 404);
    }

    /** First listed date with an open time (for the chosen provider when it matters). */
    private function firstOpenDate(array $dates, string $provider): ?string
    {
        $specialist = $this->online->isSpecialist($provider);
        foreach ($dates as $d) {
            if ($d['closed'] === null && $d['open'] > 0 && (! $specialist || in_array($provider, $d['visits'], true))) {
                return $d['date'];
            }
        }

        return null;
    }

    /** Email the clinic about the new request, when turned on (never throws). */
    private function notifyClinic(Appointment $appointment): void
    {
        if (! settings('notify_email_online_request', false)) {
            return;
        }

        $to = trim((string) settings('online_request_notify_email', '')) ?: trim((string) settings('clinic_email', ''));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Notification::route('mail', $to)->notifyNow(new OnlineAppointmentRequestNotification($appointment));
        } catch (\Throwable $e) {
            Log::warning('Online request email to the clinic failed', ['appointment_id' => $appointment->id, 'error' => $e->getMessage()]);
        }
    }

    /** Only an unambiguous match (student ID AND contact number) links automatically. */
    private function strongMatch(?string $studentId, ?string $contact): ?Patient
    {
        $studentId = trim((string) $studentId);
        if ($studentId === '' || ! $contact) {
            return null;
        }

        $tail = substr($contact, -10);
        $matches = Patient::query()
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('contact_number', 'like', "%{$tail}")->orWhere('guardian_contact', 'like', "%{$tail}"))
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
