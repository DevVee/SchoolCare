<?php

namespace App\Http\Requests\PatientLog;

use App\Models\Medicine;
use App\Models\PatientLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for creating (POST) and editing (PUT) a clinic logbook visit,
 * including the structured reasons, severity and the medicine rows.
 */
class SavePatientLogRequest extends FormRequest
{
    public const MAX_MEDICINE_ROWS = 10;

    private function current(): ?PatientLog
    {
        $log = $this->route('patient_log');

        return $log instanceof PatientLog ? $log : null;
    }

    public function authorize(): bool
    {
        return $this->user()->can($this->current() ? 'update-patient-logs' : 'create-patient-logs');
    }

    /** Drop medicine rows left completely blank before validating. */
    protected function prepareForValidation(): void
    {
        $rows = collect((array) $this->input('medicines', []))
            ->filter(fn ($r) => is_array($r) && (filled($r['medicine_id'] ?? null) || filled($r['quantity'] ?? null)))
            ->all();

        $this->merge([
            'medicines'    => $rows,
            'reasons'      => array_values(array_filter((array) $this->input('reasons', []), 'filled')),
            'other_reason' => $this->filled('other_reason') ? trim((string) $this->input('other_reason')) : null,
        ]);
    }

    public function rules(): array
    {
        $log = $this->current();

        $severities = PatientLog::severities();
        $reasons    = PatientLog::reasonOptions();
        if ($log) {
            // Choices removed from Settings stay valid on entries that already use them.
            $severities = array_filter([...$severities, $log->severity]);
            $reasons    = [...$reasons, ...(array) ($log->reasons ?? [])];
        }

        $patientExists = Rule::exists('patients', 'id')->where(
            fn ($q) => $log
                ? $q->whereNull('deleted_at')->orWhere('id', $log->patient_id)
                : $q->whereNull('deleted_at')
        );

        $dispositions = array_keys(PatientLog::dispositions());
        if ($log?->disposition) {
            $dispositions[] = $log->disposition;
        }

        return [
            'patient_id'      => ['required', 'integer', $patientExists],
            'log_date'        => ['required', 'date', 'before_or_equal:today'],
            'time_in'         => ['required', 'date_format:H:i'],
            'time_out'        => ['nullable', 'date_format:H:i', 'after:time_in'],
            'severity'        => [$log ? 'nullable' : 'required', 'string', Rule::in($severities)],
            'reasons'         => ['nullable', 'array', 'max:30'],
            'reasons.*'       => ['string', 'max:100', Rule::in($reasons)],
            'other_reason'    => ['nullable', 'string', 'max:255'],
            'chief_complaint' => ['nullable', 'string', 'max:1000'],
            'vital_temp'      => ['nullable', 'numeric', 'between:30,45'],
            'vital_bp'        => ['nullable', 'string', 'max:20'],
            'vital_pulse'     => ['nullable', 'integer', 'between:1,300'],
            'vital_weight'    => ['nullable', 'numeric', 'between:1,300'],
            'vital_height'    => ['nullable', 'numeric', 'between:1,300'],
            'assessment'      => ['nullable', 'string', 'max:1000'],
            'treatment'       => ['nullable', 'string', 'max:500'],
            'disposition'     => ['required', Rule::in($dispositions)],
            'sms_guardian'    => ['nullable'],
            'notes'           => ['nullable', 'string', 'max:500'],

            'medicines'               => ['nullable', 'array', 'max:'.self::MAX_MEDICINE_ROWS],
            'medicines.*.medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')->whereNull('deleted_at')],
            'medicines.*.quantity'    => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * - at least one reason, an "Other" reason or a free-text complaint
     * - enough usable (unexpired) stock for every medicine row, counting
     *   repeated rows of the same medicine together
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (empty($this->input('reasons')) && blank($this->input('other_reason')) && blank($this->input('chief_complaint'))) {
                    $validator->errors()->add('reasons', 'Pick at least one reason, type another reason, or describe the complaint.');
                }

                if ($validator->errors()->hasAny(['medicines', 'medicines.*'])) {
                    return;
                }

                $rows = (array) $this->input('medicines', []);
                $requested = [];
                $lastRow   = [];
                foreach ($rows as $i => $row) {
                    $id = (int) $row['medicine_id'];
                    $requested[$id] = ($requested[$id] ?? 0) + (int) $row['quantity'];
                    $lastRow[$id] = $i;
                }

                if ($requested === []) {
                    return;
                }

                $medicines = Medicine::whereIn('id', array_keys($requested))->get()->keyBy('id');
                foreach ($requested as $id => $qty) {
                    $m = $medicines->get($id);
                    if (! $m) {
                        continue;
                    }
                    $field = "medicines.{$lastRow[$id]}.quantity";

                    if (! $m->is_active) {
                        $validator->errors()->add("medicines.{$lastRow[$id]}.medicine_id", "\"{$m->name}\" is inactive and cannot be given.");
                        continue;
                    }

                    $available = $m->availableQuantity();
                    if ($available < $qty) {
                        $validator->errors()->add($field, $available === 0 && $m->quantity > 0
                            ? "All remaining stock of \"{$m->name}\" is expired."
                            : "Insufficient stock for \"{$m->name}\". Available: {$available} {$m->unit}(s), requested: {$qty}.");
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id'              => 'patient',
            'other_reason'            => 'other reason',
            'chief_complaint'         => 'complaint details',
            'medicines.*.medicine_id' => 'medicine',
            'medicines.*.quantity'    => 'quantity',
        ];
    }

    /** Validated patient_logs columns (vitals folded into JSON). */
    public function logAttributes(): array
    {
        $v = $this->validated();

        $vitals = array_filter([
            'temperature'    => $this->filled('vital_temp') ? $this->input('vital_temp') : null,
            'blood_pressure' => $this->filled('vital_bp') ? $this->input('vital_bp') : null,
            'pulse'          => $this->filled('vital_pulse') ? $this->input('vital_pulse') : null,
            'weight'         => $this->filled('vital_weight') ? $this->input('vital_weight') : null,
            'height'         => $this->filled('vital_height') ? $this->input('vital_height') : null,
        ], fn ($x) => $x !== null);

        return [
            'patient_id'      => $v['patient_id'],
            'log_date'        => $v['log_date'],
            'time_in'         => $v['time_in'],
            'time_out'        => $v['time_out'] ?? null,
            'severity'        => $v['severity'] ?? ($this->current()?->severity),
            'reasons'         => array_values(array_unique($v['reasons'] ?? [])) ?: null,
            'other_reason'    => $v['other_reason'] ?? null,
            'chief_complaint' => $v['chief_complaint'] ?? null,
            'vital_signs'     => $vitals ?: null,
            'assessment'      => $v['assessment'] ?? null,
            'treatment'       => $v['treatment'] ?? null,
            'disposition'     => $v['disposition'],
            'notes'           => $v['notes'] ?? null,
        ];
    }

    /** Medicine rows as [['medicine_id', 'quantity', 'row']]. */
    public function medicineRows(): array
    {
        $out = [];
        foreach ((array) ($this->validated()['medicines'] ?? []) as $i => $row) {
            $out[] = ['medicine_id' => (int) $row['medicine_id'], 'quantity' => (int) $row['quantity'], 'row' => $i];
        }

        return $out;
    }
}
