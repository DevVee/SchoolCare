<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\LandingItem;
use App\Models\SpecialistVisit;
use App\Support\ClinicHours;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the public website (landing page + privacy notice) shows, built
 * from the Website settings group, the landing_items / announcements tables,
 * the weekly clinic hours and upcoming specialist visit days.
 *
 * The table content is cached (CACHE_KEY) and forgotten whenever an item or
 * announcement is saved or deleted, or the Website settings are saved.
 * Settings are already cached by SettingsService; the open / closed status,
 * advisory windows and visit days are worked out on every request.
 */
class LandingContent
{
    public const CACHE_KEY = 'landing.content.v1';
    private const CACHE_TTL = 3600;

    /** Page sections that can be turned off and reordered (key => admin label). */
    public const SECTIONS = [
        'advisories' => 'Advisories',
        'services'   => 'Services',
        'schedule'   => 'Clinic hours and visit days',
        'steps'      => 'How to get care',
        'team'       => 'Clinic team',
        'faq'        => 'Common questions',
        'contact'    => 'Contact',
    ];

    /** Sections that get a link in the header (key => link text). */
    public const NAV = [
        'services'   => 'Services',
        'schedule'   => 'Clinic hours',
        'advisories' => 'Advisories',
        'faq'        => 'FAQ',
        'contact'    => 'Contact',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public static function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Cache store unavailable: nothing to forget.
        }
    }

    /** Section keys in the saved order, unknown keys dropped, missing ones appended. */
    public function sectionOrder(): array
    {
        $saved = array_values(array_intersect($this->settings->list('landing_section_order'), array_keys(self::SECTIONS)));

        return array_values(array_unique(array_merge($saved, array_keys(self::SECTIONS))));
    }

    /** Values the admin text fields fall back to when left empty (also shown as input hints). */
    public function fallbacks(): array
    {
        $n = $this->names();

        return [
            'landing_hero_heading'    => $n['clinic'],
            'landing_hero_subheading' => $n['school'] !== ''
                ? 'Health services for the students and staff of '.$n['school']
                : 'Health services for students and staff',
            'landing_copyright'       => '© '.now()->year.' '.($n['school'] !== '' ? $n['school'] : $n['clinic']),
            'landing_seo_title'       => $n['school'] !== '' ? $n['clinic'].' | '.$n['school'] : $n['clinic'],
            'landing_seo_description' => 'Clinic hours, services, advisories and contact details of the '
                .$n['clinic'].($n['school'] !== '' ? ', '.$n['school'] : '').'.',
        ];
    }

    /** Everything the landing and privacy views need. */
    public function page(): array
    {
        $names    = $this->names();
        $stored   = $this->stored();
        $now      = now();
        $booking  = (bool) $this->settings->get('public_booking_enabled') && Route::has('public.appointments.create');
        $intake   = (bool) $this->settings->get('public_intake_enabled') && Route::has('public.health-form.create');
        $fallback = $this->fallbacks();

        $advisories = array_values(array_filter($stored['announcements'], function (array $a) use ($now) {
            return ($a['starts_at'] === null || Carbon::parse($a['starts_at'])->lte($now))
                && ($a['ends_at'] === null || Carbon::parse($a['ends_at'])->gte($now));
        }));

        $steps = [];
        foreach ([1, 2, 3] as $i) {
            $title = trim((string) $this->settings->get("landing_step{$i}_title"));
            $body  = trim((string) $this->settings->get("landing_step{$i}_body"));
            if ($title !== '' || $body !== '') {
                $steps[] = ['title' => $title, 'body' => $body];
            }
        }

        $contact = $this->contact();
        $social  = $this->social();
        $week    = $this->week($now);
        $visits  = $this->visits();

        // Which sections have something to show.
        $hasContent = [
            'advisories' => $advisories !== [],
            'services'   => $stored['service'] !== [],
            'schedule'   => true,
            'steps'      => $steps !== [],
            'team'       => $stored['team'] !== [],
            'faq'        => $stored['faq'] !== [],
            'contact'    => $contact['address'] !== '' || $contact['phone'] !== '' || $contact['email'] !== ''
                            || $contact['hotline'] !== '' || $contact['map_url'] !== '' || $contact['location'] !== '' || $social !== [],
        ];

        $sections = array_values(array_filter($this->sectionOrder(), fn ($key) =>
            $this->settings->get('landing_show_'.$key, true) && $hasContent[$key]));

        $nav = [];
        foreach ($sections as $key) {
            if (isset(self::NAV[$key])) {
                $nav[$key] = self::NAV[$key];
            }
        }

        $links = [
            'request'     => $booking ? route('public.appointments.create') : null,
            'health_form' => $intake ? route('public.health-form.create') : null,
            'schedule'    => $booking && Route::has('public.schedule') ? route('public.schedule') : null,
        ];

        $primary = $this->cta('primary', $sections, $links)
            ?? ($booking ? ['label' => 'Request an appointment', 'href' => $links['request'], 'external' => false] : null)
            ?? (in_array('contact', $sections, true) ? ['label' => 'Contact the clinic', 'href' => '#contact', 'external' => false] : null)
            ?? ['label' => 'See clinic hours', 'href' => '#schedule', 'external' => false];
        $secondary = $this->cta('secondary', $sections, $links);
        if ($secondary && $secondary['href'] === $primary['href']) {
            $secondary = null;
        }

        return [
            'names'    => $names,
            'logo'     => $this->settings->imageUrl('brand_logo', '/schoolcare-icon.svg'),
            'sections' => $sections,
            'nav'      => $nav,
            'links'    => $links,
            'status'   => $this->status($week, $now),
            'today'    => $now,
            'week'     => $week,
            'hours'    => $this->hoursSummary($week),
            'visits'   => $visits,

            'hero' => [
                'heading'     => $this->text('landing_hero_heading') ?: $fallback['landing_hero_heading'],
                'subheading'  => $this->text('landing_hero_subheading') ?: $fallback['landing_hero_subheading'],
                'description' => $this->text('landing_hero_description'),
                'primary'     => $primary,
                'secondary'   => $secondary,
                'image'       => $this->heroImage(),
                'nurse'       => $this->text('landing_nurse_on_duty'),
                // Offer the health form under the buttons unless a button already opens it.
                'health_form' => $links['health_form'] !== null
                    && $primary['href'] !== $links['health_form']
                    && ($secondary['href'] ?? null) !== $links['health_form'],
            ],

            'advisories' => $advisories,
            'services'   => $stored['service'],
            'team'       => $stored['team'],
            'faqs'       => $stored['faq'],
            'steps'      => $steps,
            'contact'    => $contact,
            'social'     => $social,

            'intros' => [
                'services' => $this->text('landing_services_intro'),
                'schedule' => $this->text('landing_schedule_intro'),
                'team'     => $this->text('landing_team_intro'),
                'faq'      => $this->text('landing_faq_intro'),
                'contact'  => $this->text('landing_contact_intro'),
            ],

            'footer' => [
                'about'     => $this->text('landing_footer_about'),
                'copyright' => $this->text('landing_copyright') ?: $fallback['landing_copyright'],
                'credit'    => $this->settings->get('landing_show_credit') ? $this->text('landing_credit_text') : '',
            ],

            'privacy' => $this->paragraphs($this->text('landing_privacy_text')),

            'seo' => [
                'title'       => $this->text('landing_seo_title') ?: $fallback['landing_seo_title'],
                'description' => $this->text('landing_seo_description') ?: $fallback['landing_seo_description'],
            ],
        ];
    }

    /** "Open now until 5:00 PM" / "Closed, opens Monday 7:30 AM" for the current moment. */
    public function status(?array $week = null, ?CarbonInterface $now = null): array
    {
        $now  ??= now();
        $week ??= $this->week($now);
        $today = $week[$now->dayOfWeekIso - 1] ?? null;
        $time  = $now->format('H:i');

        if ($today && $today['hours']) {
            [$open, $close] = [$today['hours']['open'], $today['hours']['close']];
            if ($open === '00:00' && $close >= '23:59') {
                return ['open' => true, 'label' => 'Open today'];
            }
            if ($time >= $open && $time < $close) {
                return ['open' => true, 'label' => 'Open now until '.$this->time($close)];
            }
            if ($time < $open) {
                return ['open' => false, 'label' => 'Closed, opens today '.$this->time($open)];
            }
        }

        for ($i = 1; $i <= 7; $i++) {
            $day   = $now->copy()->addDays($i);
            $hours = ClinicHours::forDate($day);
            if ($hours) {
                $when = $i === 1 ? 'tomorrow' : $day->englishDayOfWeek;

                return ['open' => false, 'label' => 'Closed, opens '.$when.' '.$this->time($hours['open'])];
            }
        }

        return ['open' => false, 'label' => 'Closed'];
    }

    // ─── Pieces ──────────────────────────────────────────────────────────────

    private function names(): array
    {
        $app = trim((string) $this->settings->get('app_name'));

        return [
            'app'          => $app,
            'clinic'       => trim((string) $this->settings->get('clinic_name')) ?: ($app ?: 'School Clinic'),
            'school'       => trim((string) $this->settings->get('org_name')),
            'school_short' => trim((string) $this->settings->get('org_short_name')),
        ];
    }

    /** Enabled items and public announcements from the database (cached). */
    private function stored(): array
    {
        $empty = ['service' => [], 'faq' => [], 'team' => [], 'announcements' => []];

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $items = LandingItem::query()->enabled()->ordered()->get();
                $out = ['service' => [], 'faq' => [], 'team' => []];

                foreach ($items as $item) {
                    if (! array_key_exists($item->section, $out)) {
                        continue;
                    }
                    $out[$item->section][] = [
                        'id'         => $item->id,
                        'title'      => $item->title,
                        'subtitle'   => $item->subtitle,
                        'body'       => $item->body,
                        'icon'       => $item->icon,
                        'image'      => $item->imageUrl(),
                        'link_label' => $item->link_label,
                        'link_url'   => $item->link_url,
                    ];
                }

                $out['announcements'] = Announcement::query()->enabled()->forPublic()->ordered()->get()
                    ->map(fn (Announcement $a) => [
                        'id'         => $a->id,
                        'title'      => $a->title,
                        'body'       => $a->body,
                        'type'       => $a->type,
                        'type_label' => $a->type_label,
                        'tone'       => $a->tone,
                        'icon'       => $a->icon,
                        'link_label' => $a->link_label,
                        'link_url'   => $a->link_url,
                        'starts_at'  => $a->starts_at?->toIso8601String(),
                        'ends_at'    => $a->ends_at?->toIso8601String(),
                        'date'       => ($a->starts_at ?? $a->created_at)?->toIso8601String(),
                    ])->all();

                return $out;
            });
        } catch (\Throwable) {
            // Tables missing (before migrations) or cache unavailable: show defaults only.
            return $empty;
        }
    }

    /** Monday to Sunday: [day, label, hours|null, text, today]. */
    private function week(CarbonInterface $now): array
    {
        $rows = [];
        foreach (ClinicHours::week() as $day => $hours) {
            $rows[] = [
                'day'   => $day,
                'label' => ucfirst($day),
                'hours' => $hours,
                'text'  => $hours ? $this->time($hours['open']).' to '.$this->time($hours['close']) : 'Closed',
                'today' => strtolower($now->englishDayOfWeek) === $day,
            ];
        }

        return $rows;
    }

    /** Consecutive days with the same hours grouped: "Monday to Friday: 7:30 AM to 5:00 PM". */
    private function hoursSummary(array $week): array
    {
        $groups = [];
        foreach ($week as $row) {
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['text'] === $row['text']) {
                $groups[$last]['to'] = $row['label'];
                $groups[$last]['count']++;
                continue;
            }
            $groups[] = ['from' => $row['label'], 'to' => $row['label'], 'count' => 1, 'text' => $row['text']];
        }

        return array_map(fn ($g) => [
            'days'  => match ($g['count']) {
                1       => $g['from'],
                2       => $g['from'].' and '.$g['to'],
                default => $g['from'].' to '.$g['to'],
            },
            'text' => $g['text'],
        ], $groups);
    }

    /** Next scheduled doctor / dentist days: type, date and time only (no names, no capacity). */
    private function visits(): array
    {
        try {
            return SpecialistVisit::query()->upcoming()
                ->orderBy('visit_date')->orderBy('start_time')
                ->limit(6)
                ->get(['type', 'visit_date', 'start_time', 'end_time'])
                ->map(fn (SpecialistVisit $v) => [
                    'type'     => $v->type,
                    'date'     => $v->visit_date->format('l, F j'),
                    'date_iso' => $v->visit_date->toDateString(),
                    'time'     => $v->start_time
                        ? $this->time($v->start_time).($v->end_time ? ' to '.$this->time($v->end_time) : '')
                        : '',
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function contact(): array
    {
        $phone   = trim((string) $this->settings->get('clinic_contact'));
        $hotline = $this->text('landing_hotline');

        return [
            'address'  => trim((string) $this->settings->get('clinic_address')),
            'phone'    => $phone,
            'hotline'  => $hotline !== '' && $hotline !== $phone ? $hotline : '',
            // The number shown in the top bar: the emergency number, else the clinic phone.
            'call'     => $hotline !== '' ? $hotline : $phone,
            'email'    => trim((string) $this->settings->get('clinic_email')),
            'location' => $this->text('landing_location'),
            'map_url'  => $this->text('landing_map_url'),
        ];
    }

    private function social(): array
    {
        $out = [];
        foreach ([
            'landing_facebook_url'       => ['Facebook', 'facebook'],
            'landing_messenger_url'      => ['Messenger', 'messenger'],
            'landing_instagram_url'      => ['Instagram', 'instagram'],
            'landing_youtube_url'        => ['YouTube', 'youtube'],
            'landing_school_website_url' => ['School website', 'globe2'],
        ] as $key => [$label, $icon]) {
            $url = $this->text($key);
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $out[] = ['label' => $label, 'icon' => $icon, 'url' => $url];
            }
        }

        return $out;
    }

    private function heroImage(): ?array
    {
        $path = trim((string) $this->settings->get('landing_hero_image'));
        if ($path === '') {
            return null;
        }

        $width = 1200;
        $height = 900;
        try {
            $abs = Storage::disk('public')->path($path);
            if (! is_file($abs)) {
                return null;
            }
            $size = @getimagesize($abs);
            if ($size) {
                [$width, $height] = $size;
            }
        } catch (\Throwable) {
            return null;
        }

        return [
            'url'    => $this->settings->imageUrl('landing_hero_image'),
            'alt'    => $this->text('landing_hero_image_alt'),
            'width'  => $width,
            'height' => $height,
        ];
    }

    /** A hero button from its label / target / web address settings, or null when it cannot open anything. */
    private function cta(string $which, array $sections, array $links): ?array
    {
        $label  = $this->text("landing_{$which}_cta_label");
        $target = (string) $this->settings->get("landing_{$which}_cta_target");
        $url    = $this->text("landing_{$which}_cta_url");

        if ($label === '') {
            return null;
        }

        $href = match ($target) {
            'request'     => $links['request'],
            'health_form' => $links['health_form'],
            'schedule'    => in_array('schedule', $sections, true) ? '#schedule' : null,
            'services'    => in_array('services', $sections, true) ? '#services' : null,
            'contact'     => in_array('contact', $sections, true) ? '#contact' : null,
            'url'         => preg_match('#^https?://#i', $url) ? $url : null,
            default       => null,
        };

        return $href ? ['label' => $label, 'href' => $href, 'external' => $target === 'url'] : null;
    }

    /** A text setting, trimmed, with {clinic}, {school} and {year} filled in. */
    private function text(string $key): string
    {
        $value = trim((string) $this->settings->get($key));
        if ($value === '' || ! str_contains($value, '{')) {
            return $value;
        }
        $n = $this->names();

        return strtr($value, [
            '{clinic}' => $n['clinic'],
            '{school}' => $n['school'] !== '' ? $n['school'] : $n['clinic'],
            '{year}'   => (string) now()->year,
        ]);
    }

    /** Blank-line separated paragraphs. */
    private function paragraphs(string $text): array
    {
        $parts = preg_split("/\R\s*\R/", trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    /** "7:30 AM" (12-hour unless the clinic uses a 24-hour clock). */
    public function time(string $hhmm): string
    {
        try {
            $t = Carbon::createFromFormat('H:i', substr($hhmm, 0, 5));
        } catch (\Throwable) {
            return $hhmm;
        }

        return str_contains((string) $this->settings->get('time_format'), 'H') ? $t->format('H:i') : $t->format('g:i A');
    }
}
