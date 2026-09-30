<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\SpecialistVisit;
use App\Services\AppointmentBooking;
use App\Services\AppointmentNotifier;
use App\Services\AuditLogService;
use App\Services\SmsService;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Public appointment request (SSCMS appointments/web-new-appointment.php) and
 * the public "today's schedule" board (SSCMS appointments/index.php).
 *
 * Both are off unless Admin → Settings → Appointments → "Accept online
 * appointment requests" is on (404 otherwise). Requests are stored as pending
 * with source = online. They are linked to a patient automatically only when
 * BOTH the student ID and the contact number match one patient; otherwise
 * staff link them (or create the patient) before approving.
 */
class PublicAppointmentController extends Controller
{
    /** Name of the hidden honeypot field (real people never fill it). */
    public const HONEYPOT = 'website';

    public function __construct(private readonly AppointmentBooking $booking) {}

    public function create()
    {
        $this->ensureEnabled();

        $firstDate = $this->firstBookableDate();

        return view('public.request-appointment', [
            'purposes'   => settings()->list('appointment_purposes'),
            'providers'  => settings()->list('appointment_providers'),
            'minDate'    => today()->toDateString(),
            'maxDate'    => $this->maxDate()?->toDateString(),
            'firstDate'  => $firstDate?->toDateString(),
            'slots'      => $firstDate ? $this->booking->slotsForDate($firstDate->toDateString()) : collect(),
            'week'       => ClinicHours::week(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureEnabled();

        // Honeypot: pretend it worked, store nothing.
        if (filled($request->input(self::HONEYPOT))) {
            Log::info('Public appointment request rejected by honeypot', ['ip' => $request->ip()]);

            return redirect()->route('public.appointments.thanks');
        }

        $purposes = settings()->list('appointment_purposes');

        $data = $request->validate([
            'requester_name'       => ['required', 'string', 'min:3', 'max:150'],
            'requester_student_id' => ['nullable', 'string', 'max:50'],
            'requester_contact'    => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                if (! app(SmsService::class)->normalizeNumber($value)) {
                    $fail('Enter a valid mobile number, for example 09171234567.');
                }
            }],
            'requester_email'      => ['nullable', 'email', 'max:150'],
            'appointment_date'     => ['required', 'date', 'after_or_equal:today'],
            'appointment_time'     => ['required', 'string', Rule::exists('appointment_time_slots', 'slot_time')->where('is_active', true)],
            'purpose'              => $purposes ? ['required', 'string', Rule::in($purposes)] : ['required', 'string', 'max:150'],
            'provider'             => ['nullable', 'string', Rule::in(settings()->list('appointment_providers'))],
            'details'              => ['nullable', 'string', 'max:500'],
        ], [
            'requester_name.required'         => 'Please enter your full name.',
            'requester_name.min'              => 'Please enter your full name.',
            'requester_email.email'           => 'Enter a valid email address, or leave it empty.',
            'appointment_date.required'       => 'Choose a date for your visit.',
            'appointment_date.date'           => 'Choose a date for your visit.',
            'requester_contact.required'      => 'Please enter a mobile number so the clinic can reach you.',
            'appointment_date.after_or_equal' => 'Choose today or a later date.',
            'appointment_time.required'       => 'Choose a time slot.',
            'appointment_time.exists'         => 'Choose one of the available time slots.',
            'purpose.required'                => 'Choose the reason for your visit.',
        ]);

        $date = Carbon::parse($data['appointment_date'])->startOfDay();

        if (! ClinicHours::isOpenOn($date)) {
            throw ValidationException::withMessages(['appointment_date' => 'The clinic is closed on '.$date->format('l').'s. Please choose another date.']);
        }
        if (! settings('allow_weekend_booking', true) && $date->isWeekend()) {
            throw ValidationException::withMessages(['appointment_date' => 'Appointments cannot be requested on weekends.']);
        }
        if (($max = $this->maxDate()) && $date->gt($max)) {
            throw ValidationException::withMessages(['appointment_date' => 'Appointments can only be requested up to '.$max->format('M d, Y').'.']);
        }
        $when = Carbon::parse($date->toDateString().' '.$data['appointment_time']);
        if ($when->isToday() && $when->isPast()) {
            throw ValidationException::withMessages(['appointment_time' => 'This time has already passed today. Choose a later time.']);
        }

        $contact = app(SmsService::class)->normalizeNumber($data['requester_contact']);
        $patient = $this->strongMatch($data['requester_student_id'] ?? null, $contact);

        // A scheduled specialist day on that date for the chosen provider.
        $visitId = null;
        if (! empty($data['provider'])) {
            $visitId = SpecialistVisit::scheduled()
                ->whereDate('visit_date', $date->toDateString())
                ->where('type', $data['provider'])
                ->value('id');
        }

        $appointment = $this->booking->book([
            'patient_id'           => $patient?->id,
            'appointment_date'     => $date->toDateString(),
            'appointment_time'     => $data['appointment_time'],
            'purpose'              => $data['purpose'],
            'provider'             => $data['provider'] ?? null,
            'specialist_visit_id'  => $visitId,
            'notes'                => $data['details'] ?? null,
            'status'               => 'pending',
            'source'               => Appointment::SOURCE_ONLINE,
            'requester_name'       => trim($data['requester_name']),
            'requester_contact'    => $data['requester_contact'],
            'requester_email'      => $data['requester_email'] ?? null,
            'requester_student_id' => $data['requester_student_id'] ?? null,
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

        return redirect()->route('public.appointments.thanks')->with('request_summary', [
            'date' => $date->format('l, F d, Y'),
            'time' => Carbon::parse($data['appointment_time'])->format('h:i A'),
        ]);
    }

    public function thanks()
    {
        $this->ensureEnabled();

        return view('public.request-appointment-thanks', ['summary' => session('request_summary')]);
    }

    /** Remaining places per slot for a date (public; no patient data). */
    public function slots(Request $request)
    {
        $this->ensureEnabled();

        $request->validate(['date' => ['required', 'date']]);
        $date = Carbon::parse((string) $request->query('date'))->startOfDay();

        $open = ! $date->lt(today())
            && ClinicHours::isOpenOn($date)
            && (settings('allow_weekend_booking', true) || ! $date->isWeekend())
            && (! ($max = $this->maxDate()) || $date->lte($max));

        return response()->json([
            'date'   => $date->toDateString(),
            'open'   => $open,
            'slots'  => $open ? $this->booking->slotsForDate($date->toDateString())->map(fn ($s) => [
                'time'      => $s['slot']->slot_time,
                'label'     => $s['slot']->display_label,
                'remaining' => $s['remaining'],
                'available' => $s['available'],
            ])->values() : [],
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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function ensureEnabled(): void
    {
        abort_unless((bool) settings('public_booking_enabled', false), 404);
    }

    private function maxDate(): ?Carbon
    {
        $window = (int) settings('booking_max_days_ahead', 0);

        return $window > 0 ? today()->addDays($window) : null;
    }

    private function firstBookableDate(): ?Carbon
    {
        $max = $this->maxDate() ?? today()->addDays(60);
        for ($d = today(); $d->lte($max); $d = $d->copy()->addDay()) {
            if (ClinicHours::isOpenOn($d) && (settings('allow_weekend_booking', true) || ! $d->isWeekend())) {
                return $d;
            }
        }

        return null;
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
