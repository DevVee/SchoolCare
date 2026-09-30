<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Patient search for pickers (x-ui.patient-picker): GET patients/lookup?q=juan cruz
 *
 * Returns active, non-archived patients only (the same patients the old
 * dropdowns listed). Every word typed must match a name part, the patient
 * number or the school ID, in any order and case. Exact ID matches come first,
 * then names that start with the first word.
 *
 * {"results": [{id, label, detail, meta, ...}], "more": bool}
 */
class PatientLookupController extends Controller
{
    public const LIMIT = 20;

    public const MIN_CHARS = 2;

    /** Anyone who can see patients, or pick one on a visit form. */
    public const ABILITIES = ['view-patients', 'create-patient-logs', 'update-patient-logs'];

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canAny(self::ABILITIES), 403);

        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        // LIKE wildcards typed by the user are dropped, not matched.
        $words = array_slice(preg_split('/[\s,]+/u', str_replace(['%', '_'], '', $q), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);

        if (mb_strlen($q) < self::MIN_CHARS || $words === []) {
            return response()->json(['results' => [], 'more' => false]);
        }

        $first = $words[0];

        $patients = Patient::active()
            ->select(Patient::PICKER_COLUMNS)
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
            ->limit(self::LIMIT + 1)
            ->get();

        return response()->json([
            'results' => $patients->take(self::LIMIT)->map(fn (Patient $p) => $p->toPickerItem())->values(),
            'more'    => $patients->count() > self::LIMIT,
        ]);
    }
}
