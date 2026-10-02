<?php

namespace App\Services\Coco;

use App\Models\Patient;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Collection;

/**
 * Patient and staff search for the assistant, plus the masking used on cards.
 *
 * The patient search matches PatientLookupController (the patient picker):
 * active, non-archived patients; every word must match a name part, the
 * patient number or the school ID; exact ID matches first, then names that
 * start with the first word.
 */
class PatientFinder
{
    public const LIMIT = 5;

    public function __construct(private readonly SmsService $sms) {}

    /** @return Collection<int, Patient> */
    public function search(string $query, int $limit = self::LIMIT): Collection
    {
        $q     = trim(mb_substr($query, 0, 100));
        $words = array_slice(preg_split('/[\s,]+/u', str_replace(['%', '_'], '', $q), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);

        if (mb_strlen($q) < 2 || $words === []) {
            return collect();
        }

        $first = $words[0];

        return Patient::active()
            ->where(function ($query) use ($words) {
                foreach ($words as $word) {
                    $like = "%{$word}%";
                    $query->where(fn ($w) => $w
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('patient_number', 'like', $like)
                        ->orWhere('student_id', 'like', $like));
                }
            })
            ->orderByRaw(
                'CASE WHEN patient_number = ? OR student_id = ? THEN 0 WHEN last_name LIKE ? OR first_name LIKE ? THEN 1 ELSE 2 END',
                [$q, $q, "{$first}%", "{$first}%"]
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** An active, non-archived patient by id. */
    public function find(mixed $id): ?Patient
    {
        return is_numeric($id) ? Patient::active()->find((int) $id) : null;
    }

    /**
     * Patients the model means: the id when it gave one, else a name search.
     *
     * @return Collection<int, Patient>
     *
     * @throws CocoRefusal when nothing matches
     */
    public function resolve(mixed $id, mixed $name): Collection
    {
        if (filled($id)) {
            $patient = $this->find($id);
            if ($patient) {
                return collect([$patient]);
            }
            if (blank($name)) {
                throw CocoRefusal::because('No active patient has that id. Use find_patient to search by name.');
            }
        }

        $name = trim((string) $name);
        if ($name === '') {
            throw CocoRefusal::because('Say which patient: give patient_id from find_patient, or the name as the user wrote it.');
        }

        $matches = $this->search($name);
        if ($matches->isEmpty()) {
            throw CocoRefusal::because("No active patient matches \"{$name}\". Ask the user to check the spelling or give the patient number.");
        }

        return $matches;
    }

    /** Active staff accounts whose name or email matches every word. */
    public function staff(string $query, int $limit = self::LIMIT): Collection
    {
        $words = array_slice(preg_split('/[\s,]+/u', str_replace(['%', '_'], '', trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);
        if ($words === []) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where(fn ($w) => $w->where('name', 'like', "%{$word}%")->orWhere('email', 'like', "%{$word}%"));
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    // ── Masking ─────────────────────────────────────────────────────────────

    /** "0917 *** 4567" for a valid PH mobile number, else the last 4 digits only. */
    public function maskPhone(?string $number): string
    {
        $normalized = $this->sms->normalizeNumber($number);
        if ($normalized) {
            $local = '0'.substr($normalized, 2);

            return substr($local, 0, 4).' *** '.substr($local, -4);
        }

        $digits = preg_replace('/\D/', '', (string) $number);

        return $digits === '' ? '' : '*** '.substr($digits, -4);
    }

    /** "ma***@gmail.com" */
    public static function maskEmail(?string $email): string
    {
        $email = trim((string) $email);
        if (! str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, min(2, mb_strlen($local))).'***@'.$domain;
    }

    /** "Grade 7, Rizal. No. 2026-00012" */
    public static function describe(Patient $patient): string
    {
        return $patient->placement.'. No. '.$patient->patient_number;
    }
}
