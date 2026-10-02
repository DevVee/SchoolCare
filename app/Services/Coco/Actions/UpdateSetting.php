<?php

namespace App\Services\Coco\Actions;

use App\Models\User;
use App\Services\Coco\CocoRefusal;
use App\Services\SettingsService;
use App\Support\ClinicHours;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Change one everyday setting. Administrators only (manage-settings AND the
 * administrator role), and only keys on the list below. The value is checked
 * with the same rules as the settings page (config/settings.php) and saved
 * with SettingsService.
 *
 * Never changeable here: API keys and other secrets, security and sign-in
 * code settings, the email sender, images, roles and permissions, and the
 * assistant's own switches.
 */
class UpdateSetting extends CocoAction
{
    /** Keys the assistant may change (those missing from config/settings.php are ignored). */
    public const KEYS = [
        // Clinic details
        'clinic_name', 'clinic_address', 'clinic_contact', 'clinic_email',
        // Clinic hours and booking
        'clinic_weekly_hours', 'max_daily_appointments', 'booking_max_days_ahead', 'allow_weekend_booking',
        'reminder_hours_before', 'appointment_cancel_reason_required', 'public_booking_enabled', 'appointment_purposes',
        // Text messages
        'sms_enabled', 'sms_sender_name',
        'notify_sms_appointment_created', 'notify_sms_appointment_approved', 'notify_sms_appointment_rescheduled',
        'notify_sms_appointment_cancelled', 'notify_sms_appointment_reminder', 'sms_log_guardian_enabled',
        'notify_sms_clinic_discharge', 'notify_sms_intake_approved', 'notify_email_appointments',
        'sms_template_appointment_created', 'sms_template_approval', 'sms_template_rescheduled', 'sms_template_cancellation',
        'sms_template_reminder', 'sms_template_clinic_log', 'sms_template_discharge', 'sms_template_intake_approved',
        // The assistant
        'ai_assistant_name', 'ai_web_search',
        // Public website text
        'landing_hero_heading', 'landing_hero_subheading', 'landing_hero_description', 'landing_nurse_on_duty',
        'landing_location', 'landing_hotline', 'landing_contact_intro', 'landing_services_intro', 'landing_schedule_intro',
        'landing_team_intro', 'landing_faq_intro', 'landing_footer_about',
    ];

    /** Every setting of the online request form, whatever keys it gains later. */
    public const KEY_PREFIXES = ['public_booking_'];

    /** Never, even if listed above by mistake. */
    private const NEVER_GROUPS = ['security', 'email'];
    private const NEVER_TYPES  = ['secret', 'image'];
    private const NEVER_KEYS   = ['ai_enabled', 'ai_model', 'ai_groq_api_key', 'ai_read_patients', 'mail_from_address', 'mail_from_name'];
    private const NEVER_MATCH  = '/(api_?key|secret|password|token|otp|^ai_actions)/i';

    public function __construct(private readonly SettingsService $settings) {}

    public function type(): string { return 'update_setting'; }

    public function title(): string { return 'Change setting'; }

    public function icon(): string { return 'sliders'; }

    public function group(): string { return 'settings'; }

    public function allowedFor(User $user): bool
    {
        return $user->can('manage-settings') && $user->isAdmin();
    }

    /** @return array<string, array> key => definition, for the keys that may be changed now */
    public function editable(?User $user = null): array
    {
        $out = [];
        foreach ($this->settings->definitions() as $key => $def) {
            $listed = in_array($key, self::KEYS, true) || Str::startsWith($key, self::KEY_PREFIXES);
            if (! $listed
                || in_array($key, self::NEVER_KEYS, true)
                || preg_match(self::NEVER_MATCH, $key)
                || in_array($def['type'] ?? '', self::NEVER_TYPES, true)
                || in_array($def['group'] ?? '', self::NEVER_GROUPS, true)) {
                continue;
            }
            // Website text also needs the Website permission.
            if (($def['group'] ?? '') === 'landing' && $user && ! $user->can('manage-landing')) {
                continue;
            }
            $out[$key] = $def;
        }

        return $out;
    }

    public function tool(): array
    {
        return $this->makeTool(
            'Prepare changing ONE setting (a card; nothing changes until the user taps Confirm). Only the keys listed can be changed from the chat.',
            [
                'key'   => ['type' => 'string', 'enum' => array_keys($this->editable(auth()->user()))],
                'value' => ['type' => 'string', 'description' => 'On/off: "on" or "off". Lists: one item per line. '
                    .'clinic_weekly_hours: only the days to change, as JSON, e.g. {"saturday":"08:00-12:00","sunday":"closed"}. '
                    .'SMS templates keep their {placeholders}.'],
            ],
            ['key', 'value'],
        );
    }

    public function propose(array $args, User $user): array
    {
        $key = $this->str($args, 'key', 80);
        $this->resolve($key, $args['value'] ?? null, $user);

        return [
            'payload'    => ['key' => $key, 'value' => $args['value'] ?? null, 'candidates' => ['setting']],
            'candidates' => [],
        ];
    }

    public function plan(array $payload, ?string $choice, array $edits, User $user): array
    {
        $key = (string) $payload['key'];
        [$def, $value] = $this->resolve($key, $payload['value'] ?? null, $user);

        $group  = $this->settings->groups()[$def['group']]['label'] ?? Str::headline($def['group']);
        $before = $this->display($this->settings->get($key), $def);
        $after  = $this->display($value, $def);

        $notes = [];
        if ($def['group'] === 'landing') {
            $notes[] = 'This text is shown on the public website.';
        }
        if ($key === 'sms_enabled' && $value === true) {
            $notes[] = 'Text messages will start going out, and each uses SMS credits.';
        }

        return [
            'summary'  => 'Change '.$this->label($def)." ({$group}) from ".$this->limit($before, 80).' to '.$this->limit($after, 80),
            'fields'   => [
                ['label' => 'Setting', 'value' => $this->label($def).", under {$group}"],
                ['label' => 'Now', 'value' => $before],
                ['label' => 'Change to', 'value' => $after],
            ],
            'editable' => null,
            'notes'    => $notes,
            'key'      => $key,
            'value'    => $value,
            'label'    => $this->label($def),
        ];
    }

    public function execute(array $plan, User $user): array
    {
        $def     = $this->settings->definition($plan['key']);
        $changed = $this->settings->setMany([$plan['key'] => $plan['value']]);

        if ($changed === []) {
            return ['ok' => true, 'text' => "{$plan['label']} was already set to that."];
        }

        $old = $changed[$plan['key']]['old'] ?? null;
        $new = $changed[$plan['key']]['new'] ?? null;

        return [
            'ok'    => true,
            'text'  => "{$plan['label']} changed.",
            'url'   => $def['group'] === 'landing'
                ? (\Illuminate\Support\Facades\Route::has('admin.website.edit') ? route('admin.website.edit') : null)
                : route('admin.settings.edit', $def['group']),
            'audit' => [
                'old' => [$plan['key'] => Str::limit((string) $old, 300)],
                'new' => [$plan['key'] => Str::limit((string) $new, 300)],
            ],
        ];
    }

    // ── Value checks ────────────────────────────────────────────────────────

    /**
     * @return array{0: array, 1: mixed} the definition and the typed value to save
     *
     * @throws CocoRefusal
     */
    private function resolve(string $key, mixed $raw, User $user): array
    {
        $this->need($this->allowedFor($user), 'Only administrators can change settings.');

        $def = $this->editable($user)[$key] ?? null;
        $this->need($def !== null, $this->settings->definition($key)
            ? 'That setting cannot be changed from the chat. The user can change it in Admin > Settings.'
            : 'There is no setting called "'.$key.'".');

        $value = $this->typed($raw, $def, $key);
        $this->validate($key, $value, $def);

        $current = $this->settings->encode($this->settings->get($key), $def);
        $this->need($current !== $this->settings->encode($value, $def), $this->label($def).' is already set to that.');

        return [$def, $value];
    }

    private function typed(mixed $raw, array $def, string $key): mixed
    {
        switch ($def['type']) {
            case 'boolean':
                $bool = is_bool($raw) ? $raw : filter_var(is_scalar($raw) ? (string) $raw : '', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $this->need($bool !== null, 'Say "on" or "off" for '.$this->label($def).'.');

                return $bool;

            case 'integer':
                $this->need(is_numeric($raw) && (int) $raw == $raw, $this->label($def).' needs a whole number.');

                return (int) $raw;

            case 'json_list':
                return $this->settings->toList(is_array($raw) ? $raw : $this->plain((string) (is_scalar($raw) ? $raw : '')));

            case 'options':
                return $key === 'clinic_weekly_hours' ? $this->weeklyHours($raw) : $this->settings->toOptions($raw);

            case 'select':
                $options = is_array($def['options'] ?? null) ? $def['options'] : [];
                $value   = is_scalar($raw) ? trim((string) $raw) : '';
                if (! array_key_exists($value, $options)) {
                    // Accept the label too ("Required" for "required").
                    $match = collect($options)->search(fn ($label) => Str::lower((string) $label) === Str::lower($value));
                    $this->need($match !== false, 'Choose one of: '.implode(', ', $options).'.');
                    $value = (string) $match;
                }

                return $value;

            default:
                $this->need(is_scalar($raw) || $raw === null, $this->label($def).' needs plain text.');

                return $this->plain((string) $raw);
        }
    }

    /** Merge the days given into the current hours; each day "HH:MM-HH:MM" or "closed". */
    private function weeklyHours(mixed $raw): array
    {
        $given = is_array($raw) ? $raw : json_decode(is_scalar($raw) ? (string) $raw : '', true);
        if (! is_array($given)) {
            // Lines like "saturday: 08:00-12:00" or "sunday | closed".
            $given = [];
            foreach (preg_split('/\R|;/', is_scalar($raw) ? (string) $raw : '') ?: [] as $line) {
                if (preg_match('/^\s*([a-z]+)\s*[:|=]?\s*(.+?)\s*$/i', $line, $m)) {
                    $given[$m[1]] = $m[2];
                }
            }
            $this->need($given !== [], 'Give the hours like {"saturday": "08:00-12:00"} or {"sunday": "closed"}.');
        }

        $hours = $this->settings->options('clinic_weekly_hours');
        foreach ($given as $day => $value) {
            $day = Str::lower(trim((string) $day));
            $this->need(in_array($day, ClinicHours::DAYS, true), "\"{$day}\" is not a day of the week.");

            $value = Str::lower(trim(str_replace(['–', '—', ' to '], '-', (string) $value)));
            if ($value === 'closed') {
                $hours[$day] = 'closed';
                continue;
            }

            $this->need((bool) preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $value, $m), 'Give hours like 07:30-17:00, or "closed".');
            $open  = sprintf('%02d:%02d', $m[1], $m[2]);
            $close = sprintf('%02d:%02d', $m[3], $m[4]);
            $this->need($m[1] < 24 && $m[3] < 24 && $m[2] < 60 && $m[4] < 60 && $open < $close, 'The closing time must be after the opening time.');
            $hours[$day] = "{$open}-{$close}";
        }

        // Keep Monday to Sunday order.
        return collect(ClinicHours::DAYS)->mapWithKeys(fn ($d) => [$d => $hours[$d] ?? 'closed'])->all();
    }

    /** The settings page's own rules for this field (UpdateSettingsRequest). */
    private function validate(string $key, mixed $value, array $def): void
    {
        $rules = $def['rules'] ?? ['nullable'];
        $input = match ($def['type']) {
            'json_list' => implode("\n", (array) $value),
            'options'   => collect((array) $value)->map(fn ($l, $v) => "{$v} | {$l}")->implode("\n"),
            default     => $value,
        };

        if (in_array($def['type'], ['json_list', 'options'], true)) {
            $this->need(count((array) $value) <= 300, $this->label($def).': at most 300 entries.');
            $this->need(! in_array('required', $rules, true) || count((array) $value) > 0, $this->label($def).' needs at least one entry.');
        }

        if (! empty($def['placeholders'])) {
            $allowed = array_merge($def['placeholders'], config('settings.sms_globals', []));
            preg_match_all('/\{([a-z_]+)\}/i', (string) $value, $m);
            $unknown = array_diff(array_unique($m[1]), $allowed);
            $this->need($unknown === [], 'Unknown placeholder {'.implode('}, {', $unknown).'}. Allowed: {'.implode('}, {', $allowed).'}.');
        }

        $validator = Validator::make([$key => $input], [$key => $rules], [], [$key => $this->label($def)]);
        if ($validator->fails()) {
            throw CocoRefusal::because($validator->errors()->first($key));
        }
    }

    private function display(mixed $value, array $def): string
    {
        $text = match ($def['type']) {
            'boolean'   => $value ? 'On' : 'Off',
            'json_list' => implode(', ', (array) $value),
            'options'   => collect((array) $value)->map(fn ($l, $v) => Str::headline((string) $v).': '.$l)->implode(', '),
            'select'    => (string) ((is_array($def['options'] ?? null) ? ($def['options'][(string) $value] ?? null) : null) ?: $value),
            default     => (string) $value,
        };

        return $text === '' ? '(empty)' : $text;
    }

    private function label(array $def): string
    {
        return (string) ($def['label'] ?? 'Setting');
    }
}
