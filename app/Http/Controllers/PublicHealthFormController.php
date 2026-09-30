<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\PatientRules;
use App\Models\Patient;
use App\Models\PatientIntakeSubmission;
use App\Services\SmsService;
use App\Support\AcademicLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

/**
 * Public Student Health Information Form (/health-form).
 *
 * Off unless Admin → Settings → Online Health Form is on (404 otherwise).
 * Submissions are stored in patient_intake_submissions as pending; this
 * controller never writes to the patients table.
 */
class PublicHealthFormController extends Controller
{
    public const HONEYPOT = 'website';

    /** Phone fields that must be valid PH mobile numbers when filled. */
    private const MOBILE_FIELDS = ['contact_number', 'guardian_contact'];

    public function create()
    {
        $this->ensureEnabled();

        return view('public.health-form', [
            'categoryLabels' => Patient::categoryLabels(),
            'sexLabels'      => Patient::sexLabels(),
            'bloodTypes'     => Patient::bloodTypes(),
            'academic'       => AcademicLists::clientConfig(),
            'consentText'    => (string) settings('intake_consent_text', ''),
        ]);
    }

    public function store(Request $request, SmsService $sms)
    {
        $this->ensureEnabled();

        if (filled($request->input(self::HONEYPOT))) {
            Log::info('Public health form rejected by honeypot', ['ip' => $request->ip()]);

            return redirect()->route('public.health-form.thanks');
        }

        $rules = PatientRules::rules();
        // No uniqueness check here: a returning student may send an update,
        // and the public form must not reveal whether an ID is registered.
        $rules['student_id'] = ['nullable', 'string', 'max:50'];
        $rules['consent']    = ['accepted'];

        $validator = validator($request->all(), $rules, PatientRules::messages() + [
            'consent.accepted' => 'Please read and accept the consent statement to send the form.',
        ]);
        $validator->after(PatientRules::academicCheck(fn (string $key) => $request->input($key)));
        $validator->after(function (Validator $v) use ($request, $sms) {
            foreach (self::MOBILE_FIELDS as $field) {
                if (filled($request->input($field)) && ! $sms->normalizeNumber($request->input($field))) {
                    $v->errors()->add($field, 'Enter a valid mobile number, for example 09171234567.');
                }
            }
        });

        $data = $validator->validate();
        unset($data['consent']);

        // Store mobile numbers in the local 09XXXXXXXXX form used by staff.
        foreach (self::MOBILE_FIELDS as $field) {
            if (! empty($data[$field]) && ($n = $sms->normalizeNumber($data[$field]))) {
                $data[$field] = '0'.substr($n, 2);
            }
        }

        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        $data = array_filter($data, fn ($v) => $v !== null && $v !== '');

        PatientIntakeSubmission::create([
            'payload'      => $data,
            'student_id'   => $data['student_id'] ?? null,
            'last_name'    => $data['last_name'],
            'first_name'   => $data['first_name'],
            'birthdate'    => $data['birthdate'] ?? null,
            'contact'      => $data['contact_number'] ?? ($data['guardian_contact'] ?? null),
            'status'       => 'pending',
            'submitted_ip' => $request->ip(),
            'consent_at'   => now(),
        ]);

        return redirect()->route('public.health-form.thanks');
    }

    public function thanks()
    {
        $this->ensureEnabled();

        return view('public.health-form-thanks', [
            'message' => (string) settings('intake_success_message', ''),
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) settings('public_intake_enabled', false), 404);
    }
}
