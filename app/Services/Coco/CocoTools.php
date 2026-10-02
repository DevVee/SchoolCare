<?php

namespace App\Services\Coco;

use App\Http\Controllers\PatientLookupController;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Models\AiPendingAction;
use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\User;
use App\Services\AppointmentBooking;
use App\Services\AuditLogService;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The function tools the assistant is offered (Groq tool calling) and what
 * each one does when the model calls it.
 *
 *   Read tools answer from the records, within the user's permissions, and
 *   are recorded in the audit log. Patient details go to Groq only while
 *   "Let the assistant read patient records" is on.
 *   Action tools only create a proposal (CocoActions::propose); a person
 *   must confirm it on the card.
 */
class CocoTools
{
    public function __construct(
        private readonly CocoActions $actions,
        private readonly PatientFinder $finder,
        private readonly AppointmentBooking $booking,
    ) {}

    /** Admin > Settings > AI Assistant > "Let the assistant read patient records" (on by default). */
    public static function readPatientsEnabled(): bool
    {
        return (bool) settings('ai_read_patients', true);
    }

    /**
     * Tools offered to this user now: read tools per permission, action tools
     * per the admin switches and permission.
     *
     * @return array<int, array> OpenAI-format function tools
     */
    public function definitions(User $user): array
    {
        $tools = [];
        $read  = self::readPatientsEnabled();

        if ($read && $user->canAny(PatientLookupController::ABILITIES)) {
            $tools[] = $this->fn('find_patient', 'Search patients by name, patient number or school ID. Up to 5 matches with id and placement.', [
                'query' => ['type' => 'string'],
            ], ['query']);
        }

        if ($read && $user->can('view-patients')) {
            $tools[] = $this->fn('get_patient_summary', 'One patient: placement, allergies, existing conditions, last 3 clinic visits.', [
                'patient_id' => ['type' => 'integer'],
            ], ['patient_id']);
        }

        if ($user->can('view-appointments')) {
            $tools[] = $this->fn('list_appointment_slots', 'Free appointment times on a date, clinic hours and booking rules.', [
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, default today.'],
            ]);
            $tools[] = $this->fn('list_appointments', "A day's appointments, or a patient's upcoming ones, with ids.", [
                'date'       => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'patient_id' => ['type' => 'integer'],
            ]);
        }

        if ($user->can('view-medicines')) {
            $tools[] = $this->fn('medicine_stock', 'Medicine stock, usable units, reorder level and expiry. No name: the low-stock list.', [
                'name' => ['type' => 'string'],
            ]);
        }

        foreach ($this->actions->available($user) as $handler) {
            $tools[] = $handler->tool();
        }

        return $tools;
    }

    /**
     * Run one tool call from the model.
     *
     * @param  array{proposals:int, seen:array<string,AiPendingAction>}  $turn  state for this chat message
     * @return array{content: string, action: ?AiPendingAction}
     */
    public function run(string $name, array $args, User $user, array &$turn): array
    {
        $offered = collect($this->definitions($user))->pluck('function.name')->all();
        if (! in_array($name, $offered, true)) {
            return $this->out(['error' => "The tool {$name} is not available."]);
        }

        if ($this->actions->isAction($name)) {
            return $this->propose($name, $args, $user, $turn);
        }

        try {
            return $this->out(match ($name) {
                'find_patient'           => $this->findPatient($args, $user),
                'get_patient_summary'    => $this->patientSummary($args, $user),
                'list_appointment_slots' => $this->slots($args, $user),
                'list_appointments'      => $this->appointments($args, $user),
                'medicine_stock'         => $this->medicineStock($args, $user),
            });
        } catch (\Throwable $e) {
            Log::warning('Assistant tool failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return $this->out(['error' => 'The records could not be read just now.']);
        }
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    private function propose(string $name, array $args, User $user, array &$turn): array
    {
        $key = $name.':'.md5(json_encode($args));
        if (isset($turn['seen'][$key])) {
            return $this->out($this->actions->modelNote($turn['seen'][$key]));
        }

        if (($turn['proposals'] ?? 0) >= CocoActions::PER_TURN) {
            return $this->out(['status' => 'not_possible', 'reason' => 'Only '.CocoActions::PER_TURN.' cards can be prepared per message. Ask the user to send the next one in a new message.']);
        }

        try {
            $action = $this->actions->propose($name, $args, $user);
        } catch (CocoRefusal $e) {
            return $this->out(['status' => 'not_possible', 'reason' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('Assistant proposal failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return $this->out(['status' => 'not_possible', 'reason' => 'It could not be prepared just now. Suggest doing it on its page in the system.']);
        }

        $turn['proposals'] = ($turn['proposals'] ?? 0) + 1;
        $turn['seen'][$key] = $action;

        return ['content' => $this->json($this->actions->modelNote($action)), 'action' => $action];
    }

    // ── Read tools ──────────────────────────────────────────────────────────

    private function findPatient(array $args, User $user): array
    {
        $query   = trim(mb_substr((string) ($args['query'] ?? ''), 0, 100));
        $matches = $this->finder->search($query);

        $this->audit($matches->isEmpty()
            ? "searched patients for \"{$query}\" for {$user->name} (no match)"
            : 'looked up '.$matches->map(fn (Patient $p) => "{$p->full_name} ({$p->patient_number})")->implode(', ')." for {$user->name}");

        return [
            'matches' => $matches->map(fn (Patient $p) => [
                'id'         => $p->id,
                'name'       => $p->full_name,
                'patient_no' => $p->patient_number,
                'placement'  => $p->placement,
            ])->values()->all(),
            'note' => $matches->isEmpty()
                ? 'No active patient matches. Ask the user to check the spelling or give the patient number.'
                : ($matches->count() >= PatientFinder::LIMIT ? 'Only the first '.PatientFinder::LIMIT.' matches are listed.' : null),
        ];
    }

    private function patientSummary(array $args, User $user): array
    {
        $patient = is_numeric($args['patient_id'] ?? null) ? Patient::query()->find((int) $args['patient_id']) : null;
        if (! $patient) {
            return ['error' => 'No patient has that id. Use find_patient first.'];
        }

        $visits = $patient->patientLogs()
            ->orderByDesc('log_date')->orderByDesc('time_in')
            ->limit(3)
            ->get();

        $this->audit("looked up {$patient->full_name} ({$patient->patient_number}) for {$user->name}");

        return array_filter([
            'id'          => $patient->id,
            'name'        => $patient->full_name,
            'patient_no'  => $patient->patient_number,
            'placement'   => $patient->placement,
            'active'      => $patient->is_active ? null : false,
            'allergies'   => filled($patient->allergies) ? $patient->allergies : 'None recorded',
            'conditions'  => filled($patient->medical_conditions) ? $patient->medical_conditions : 'None recorded',
            'last_visits' => $visits->map(fn (PatientLog $v) => array_filter([
                'date'      => $v->log_date?->format('M j, Y'),
                'complaint' => $v->complaint_summary ?: null,
                'action'    => $v->treatment ?: null,
                'outcome'   => $v->disposition ? $v->disposition_label : null,
            ]))->values()->all() ?: 'No clinic visits recorded.',
            'on_file'     => [
                'patient_mobile'  => filled($patient->contact_number),
                'guardian_mobile' => filled($patient->guardian_contact),
                'email'           => filled($patient->email),
            ],
        ], fn ($v) => $v !== null);
    }

    private function slots(array $args, User $user): array
    {
        $day   = $this->day($args['date'] ?? null);
        $date  = $day->toDateString();
        $slots = $this->booking->slotsForDate($date);
        $hours = ClinicHours::forDate($day);

        $this->audit('checked the appointment times on '.$day->format('M j, Y')." for {$user->name}");

        return array_filter([
            'date'         => $day->format('l, F j, Y').' ('.$date.')',
            'clinic_hours' => $hours ? "{$hours['open']} to {$hours['close']}" : 'closed',
            'blocked'      => $day->lt(today()) ? ['The date has passed.'] : (StoreAppointmentRequest::bookingLimitErrors($date) ?: null),
            'free'         => $slots->filter(fn ($s) => $s['available'])->map(fn ($s) => substr((string) $s['slot']->slot_time, 0, 5)." ({$s['remaining']} left)")->values()->all(),
            'full'         => $slots->filter(fn ($s) => ! $s['past'] && $s['remaining'] < 1)->map(fn ($s) => substr((string) $s['slot']->slot_time, 0, 5))->values()->all() ?: null,
            'note'         => $slots->isEmpty() ? 'No appointment times are set up for this day.' : null,
        ], fn ($v) => $v !== null);
    }

    private function appointments(array $args, User $user): array
    {
        $read    = self::readPatientsEnabled();
        $patient = is_numeric($args['patient_id'] ?? null) ? Patient::query()->find((int) $args['patient_id']) : null;
        $day     = filled($args['date'] ?? null) || ! $patient ? $this->day($args['date'] ?? null) : null;

        $rows = Appointment::with('patient')
            ->when($patient, fn ($q) => $q->where('patient_id', $patient->id))
            ->when($day, fn ($q) => $q->whereDate('appointment_date', $day->toDateString()))
            ->when(! $day, fn ($q) => $q->whereDate('appointment_date', '>=', today())->whereIn('status', ['pending', 'approved']))
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(25)
            ->get();

        $this->audit('looked at '.($patient && $read ? "{$patient->full_name}'s appointments" : 'the appointments')
            .($day ? ' on '.$day->format('M j, Y') : '')." for {$user->name}");

        return [
            'appointments' => $rows->map(fn (Appointment $a) => array_filter([
                'id'       => $a->id,
                'date'     => $a->appointment_date->toDateString(),
                'time'     => substr((string) $a->appointment_time, 0, 5),
                'status'   => $a->status,
                'purpose'  => $a->purpose,
                'with'     => $a->provider,
                'patient'  => $read ? $a->display_name : null,
                'online'   => $a->needsPatientLink() ? 'online request, not linked to a patient yet' : null,
            ]))->values()->all(),
            'note' => $rows->isEmpty() ? 'No appointments found.' : ($rows->count() >= 25 ? 'Only the first 25 are listed.' : null),
        ];
    }

    private function medicineStock(array $args, User $user): array
    {
        $name = trim(mb_substr((string) ($args['name'] ?? ''), 0, 100));

        $medicines = Medicine::query()
            ->when($name !== '', fn ($q) => $q->search($name), fn ($q) => $q->active()->lowStock())
            ->orderBy('name')
            ->limit($name !== '' ? 5 : 10)
            ->get();

        $this->audit(($name !== '' ? "checked the stock of \"{$name}\"" : 'checked the medicines low on stock')." for {$user->name}");

        return [
            'medicines' => $medicines->map(fn (Medicine $m) => array_filter([
                'name'          => $m->name,
                'generic'       => $m->generic_name,
                'unit'          => $m->unit,
                'on_hand'       => (int) $m->quantity,
                'can_be_given'  => $m->availableQuantity(),
                'expired_units' => $m->expiredQuantity() ?: null,
                'reorder_level' => (int) $m->low_stock_threshold,
                'low_stock'     => $m->is_low_stock,
                'next_expiry'   => $m->expiration_date?->toDateString(),
                'inactive'      => $m->is_active ? null : true,
            ], fn ($v) => $v !== null))->values()->all(),
            'note' => $medicines->isEmpty() ? ($name !== '' ? 'No medicine matches that name.' : 'No medicine is low on stock.') : null,
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function fn(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $name,
                'description' => $description,
                'parameters'  => ['type' => 'object', 'properties' => $properties, 'required' => $required],
            ],
        ];
    }

    private function day(mixed $value): Carbon
    {
        try {
            return filled($value) ? Carbon::parse((string) $value)->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }

    /** "Coco looked up Maria Santos (2026-00012) for Ana Reyes" in the audit logs. */
    private function audit(string $what): void
    {
        AuditLogService::log(
            action: 'viewed',
            module: 'ai-assistant',
            description: Str::limit(CocoActions::assistantName().' '.$what, 1000),
        );
    }

    private function out(array $data): array
    {
        return ['content' => $this->json($data), 'action' => null];
    }

    private function json(array $data): string
    {
        return json_encode(array_filter($data, fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
