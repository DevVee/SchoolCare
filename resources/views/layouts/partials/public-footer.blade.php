{{--
    Footer of the public pages (layouts.public): the clinic and one line about it,
    today's hours with a live open or closed status, quick links, the clinic's
    contact details (only those that are set) and the copyright line.
    Names come from Settings (clinic_name, org_name), never the product name.
    Text: Administration > Website (footer text, copyright line, emergency number).
--}}
@php
    $landing = app(\App\Services\LandingContent::class);
    $fClinic = trim((string) settings('clinic_name')) ?: 'School Clinic';
    $fSchool = trim((string) settings('org_name'));
    $fFill   = fn (string $text) => strtr($text, ['{clinic}' => $fClinic, '{school}' => $fSchool !== '' ? $fSchool : $fClinic, '{year}' => (string) now()->year]);
    $fAbout  = $fFill(trim((string) settings('landing_footer_about', '')));
    $fCopy   = $fFill(trim((string) settings('landing_copyright', ''))) ?: '© '.now()->year.' '.($fSchool !== '' ? $fSchool : $fClinic);

    // Today: status ("Open now until 5:00 PM") and hours; the week grouped ("Monday to Friday").
    $fStatus = $landing->status();
    $fWeek   = \App\Support\ClinicHours::week();
    $fToday  = strtolower(now()->englishDayOfWeek);
    $fHours  = $fWeek[$fToday] ?? null;
    $fTime   = fn (array $h) => ($h['open'] === '00:00' && $h['close'] >= '23:59') ? 'Open all day' : $landing->time($h['open']).' to '.$landing->time($h['close']);
    $fGroups = [];
    foreach ($fWeek as $day => $h) {
        $text = $h ? $fTime($h) : 'Closed';
        $last = array_key_last($fGroups);
        if ($last !== null && $fGroups[$last]['text'] === $text) {
            $fGroups[$last]['to'] = ucfirst($day);
            $fGroups[$last]['count']++;
            continue;
        }
        $fGroups[] = ['from' => ucfirst($day), 'to' => ucfirst($day), 'count' => 1, 'text' => $text];
    }
    foreach ($fGroups as $i => $g) {
        $fGroups[$i]['days'] = match ($g['count']) {
            1       => $g['from'],
            2       => $g['from'].' and '.$g['to'],
            default => $g['from'].' to '.$g['to'],
        };
    }

    // Contact: only what is set.
    $fAddress = trim((string) settings('clinic_address'));
    $fPhone   = trim((string) settings('clinic_contact'));
    $fEmail   = trim((string) settings('clinic_email'));
    $fHotline = trim((string) settings('landing_hotline'));
    if ($fHotline === $fPhone) {
        $fHotline = '';
    }

    $fLinks = [['label' => 'Clinic page', 'href' => route('clinic')]];
    if (settings('public_booking_enabled', false)) {
        $fLinks[] = ['label' => 'Request an appointment', 'href' => route('public.appointments.create')];
        $fLinks[] = ['label' => 'Clinic schedule', 'href' => route('public.schedule')];
    }
    if (settings('public_intake_enabled', false)) {
        $fLinks[] = ['label' => 'Health form', 'href' => route('public.health-form.create')];
    }
    $fLinks[] = ['label' => 'Privacy notice', 'href' => route('privacy')];
    $fHasContact = $fAddress !== '' || $fPhone !== '' || $fEmail !== '' || $fHotline !== '';
@endphp
<footer class="pub-footer">
    <div class="pub-container">
        <div @class(['pub-footer-grid', 'has-contact' => $fHasContact])>
            <div class="pub-footer-brand">
                <a href="{{ route('clinic') }}" class="pub-brand">
                    <img src="{{ settings()->imageUrl('brand_logo') }}" alt="" width="36" height="36" class="pub-brand-logo" loading="lazy" decoding="async">
                    <span class="pub-brand-text">
                        <span class="pub-brand-name">{{ $fClinic }}</span>
                        @if ($fSchool !== '')<span class="pub-brand-sub">{{ $fSchool }}</span>@endif
                    </span>
                </a>
                @if ($fAbout !== '')
                    <p class="pub-footer-about">{{ $fAbout }}</p>
                @endif
            </div>

            <section class="pub-footer-hours" aria-labelledby="pubFooterHours">
                <h2 class="pub-footer-title" id="pubFooterHours">Clinic hours</h2>
                <p @class(['pub-footer-status', 'is-open' => $fStatus['open']])>
                    <span @class(['c-live' => $fStatus['open'], 'pub-footer-dot' => ! $fStatus['open']]) aria-hidden="true"></span>
                    {{ $fStatus['label'] }}
                </p>
                <p class="pub-footer-today">Today, {{ ucfirst($fToday) }}: {{ $fHours ? $fTime($fHours) : 'Closed' }}</p>
                <dl class="pub-footer-week">
                    @foreach ($fGroups as $g)
                        <div>
                            <dt>{{ $g['days'] }}</dt>
                            <dd>{{ $g['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <nav class="pub-footer-nav" aria-labelledby="pubFooterLinks">
                <h2 class="pub-footer-title" id="pubFooterLinks">Quick links</h2>
                <ul class="pub-footer-list">
                    @foreach ($fLinks as $l)
                        <li><a href="{{ $l['href'] }}">{{ $l['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            @if ($fHasContact)
                <div class="pub-footer-contact">
                    <h2 class="pub-footer-title">Contact the clinic</h2>
                    <ul class="pub-footer-list">
                        @if ($fAddress !== '')
                            <li><span class="pub-footer-address"><x-ui.icon name="geo-alt" />{{ $fAddress }}</span></li>
                        @endif
                        @if ($fPhone !== '')
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $fPhone) }}"><x-ui.icon name="telephone" />{{ $fPhone }}</a></li>
                        @endif
                        @if ($fHotline !== '')
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $fHotline) }}"><x-ui.icon name="exclamation-circle" />Emergency: {{ $fHotline }}</a></li>
                        @endif
                        @if ($fEmail !== '')
                            <li><a href="mailto:{{ $fEmail }}"><x-ui.icon name="envelope" />{{ $fEmail }}</a></li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>

        <div class="pub-footer-bottom">
            <p>{{ $fCopy }}</p>
            <p><a href="{{ route('privacy') }}">Privacy notice</a></p>
        </div>
    </div>
</footer>
