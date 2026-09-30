{{--
    Admin > Settings > Printing: letterhead banner, signatories and footer of printed
    documents (clinic reports and the individual health record). Read by
    App\Support\PrintBranding and printed by reports/pdf/_letterhead and _signatures.
    Screen styles: resources/scss/pages/_print-settings.scss. The live previews are
    drawn by the script at the bottom, before anything is saved.
--}}
@use('App\Support\PrintBranding')
@php
    $contentWidth = PrintBranding::contentWidth(PrintBranding::REPORTS);

    $bannerPath = (string) $settings->get('print_banner', '');
    $bannerUrl = $bannerPath !== '' ? $settings->imageUrl('print_banner') : '';
    $bannerHeight = max(15, min(60, (int) old('print_banner_height', $settings->get('print_banner_height'))));
    $bannerFit = (string) old('print_banner_fit', $settings->get('print_banner_fit'));
    $bannerAlign = (string) old('print_banner_align', $settings->get('print_banner_align'));

    // Same names and logo as the printed letterhead (never the product name or mark).
    $orgName = PrintBranding::orgName();
    $clinicName = trim((string) settings('clinic_name', ''));
    $clinicName = $clinicName !== $orgName ? $clinicName : '';
    if ($orgName === '' && $clinicName === '') {
        $clinicName = PrintBranding::FALLBACK_NAME;
    }
    $logoUrl = PrintBranding::logoUrl();

    $imageAccept = '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp';
    $alignIcons = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];
    $val = fn (string $key) => old($key, (string) $settings->get($key, ''));
@endphp

<div class="print-settings" data-print-settings data-content-width="{{ $contentWidth }}">

{{-- ── Letterhead ───────────────────────────────────────────────────────── --}}
<x-ui.section title="Letterhead" description="A banner printed at the top of reports and health records, in place of the logo and clinic name. Without a banner, documents print the logo and clinic name as they do now.">
    <div class="row g-4">
        <div class="col-12 col-xl-6 vstack gap-3">

            <x-ui.field label="Banner image" name="print_banner" for="setting_print_banner" :help="$fields['print_banner']['help'] ?? null">
                <div class="print-upload" data-print-image data-saved-src="{{ $bannerUrl }}" data-max-bytes="{{ 5 * 1024 * 1024 }}" data-noun="banner">
                    <input type="file" name="print_banner" id="setting_print_banner" accept="{{ $imageAccept }}" data-image-file
                           @class(['form-control', 'is-invalid' => $errors->has('print_banner')])
                           @if ($errors->has('print_banner')) aria-invalid="true" aria-describedby="setting_print_banner-error" @endif>
                    <input type="hidden" name="remove_print_banner" value="0" data-image-remove-input>
                    <div class="print-upload-actions">
                        <x-ui.button variant="danger" size="sm" icon="trash3" data-image-remove :hidden="$bannerUrl === ''">Remove banner</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" icon="arrow-counterclockwise" data-image-undo hidden>Undo</x-ui.button>
                        <span class="print-upload-status" data-image-status role="status" aria-live="polite"></span>
                    </div>
                </div>
            </x-ui.field>

            <x-ui.field label="Banner height" name="print_banner_height" for="setting_print_banner_height"
                        help="From 15 to 60 millimetres. With Full page width, this is the most the banner may take.">
                <div class="print-range">
                    <input type="range" class="form-range" min="15" max="60" step="1" value="{{ $bannerHeight }}"
                           aria-label="Banner height in millimetres" data-banner-range>
                    <div class="input-group print-range-number">
                        <input type="number" name="print_banner_height" id="setting_print_banner_height" min="15" max="60" step="1" inputmode="numeric"
                               value="{{ $bannerHeight }}" data-banner-height
                               @class(['form-control', 'is-invalid' => $errors->has('print_banner_height')])
                               aria-describedby="setting_print_banner_height-help">
                        <span class="input-group-text">mm</span>
                    </div>
                </div>
            </x-ui.field>

            <fieldset class="c-field print-choice">
                <legend class="form-label">Banner size</legend>
                <div class="print-segmented">
                    @foreach ($fields['print_banner_fit']['options'] ?? [] as $value => $label)
                        <input type="radio" class="btn-check" name="print_banner_fit" id="print_banner_fit_{{ $value }}" value="{{ $value }}"
                               autocomplete="off" @checked($bannerFit === $value)>
                        <label class="print-segment" for="print_banner_fit_{{ $value }}">{{ $label }}</label>
                    @endforeach
                </div>
                <div class="form-text">Full page width spans the page between the margins. Natural size keeps the height you set.</div>
                <x-ui.field-error name="print_banner_fit" />
            </fieldset>

            <fieldset class="c-field print-choice">
                <legend class="form-label">Banner position</legend>
                <div class="print-segmented">
                    @foreach ($fields['print_banner_align']['options'] ?? [] as $value => $label)
                        <input type="radio" class="btn-check" name="print_banner_align" id="print_banner_align_{{ $value }}" value="{{ $value }}"
                               autocomplete="off" @checked($bannerAlign === $value)>
                        <label class="print-segment" for="print_banner_align_{{ $value }}">
                            <x-ui.icon :name="$alignIcons[$value] ?? 'text-center'" />{{ $label }}
                        </label>
                    @endforeach
                </div>
                <div class="form-text">Used when the banner is narrower than the page.</div>
                <x-ui.field-error name="print_banner_align" />
            </fieldset>
        </div>

        {{-- Live preview: the top of an A4 page, drawn to scale. --}}
        <div class="col-12 col-xl-6">
            <figure class="print-preview" aria-labelledby="print-preview-caption">
                <div class="print-paper" data-paper
                     style="--print-margin-x: {{ round(14 / 210 * 100, 4) }}%; --print-margin-top: {{ round(16 / 210 * 100, 4) }}%;">
                    <div class="print-paper-body">
                        <div class="print-paper-banner" data-preview-banner @if ($bannerUrl === '') hidden @endif>
                            <img src="{{ $bannerUrl }}" alt="Letterhead banner preview" data-preview-image @if ($bannerUrl === '') hidden @endif>
                        </div>
                        <div class="print-paper-default" data-preview-default @if ($bannerUrl !== '') hidden @endif>
                            @if ($logoUrl)<img src="{{ $logoUrl }}" alt="" class="print-paper-logo">@endif
                            <div class="min-w-0">
                                @if ($orgName !== '')<div class="print-paper-org">{{ $orgName }}</div>@endif
                                @if ($clinicName !== '')<div class="print-paper-clinic">{{ $clinicName }}</div>@endif
                                <div class="print-paper-bar" style="width: 70%;"></div>
                            </div>
                        </div>
                        <div class="print-paper-title">
                            <span>Monthly report</span>
                            <span class="print-paper-period">{{ now()->format('F Y') }}</span>
                        </div>
                        <div class="print-paper-rule"></div>
                        <div class="print-paper-bar" style="width: 92%;"></div>
                        <div class="print-paper-bar" style="width: 84%;"></div>
                        <div class="print-paper-bar" style="width: 88%;"></div>
                        <div class="print-paper-bar" style="width: 60%;"></div>
                    </div>
                </div>
                <figcaption class="print-preview-caption" id="print-preview-caption" data-preview-caption>
                    Preview of the top of an A4 page.
                </figcaption>
            </figure>
        </div>
    </div>
</x-ui.section>

{{-- ── Signatures ───────────────────────────────────────────────────────── --}}
<x-ui.section title="Signatures" description="Who signs printed documents. Signatures print at the end of the document, side by side. A signatory prints only when a name is filled in.">
    <div class="vstack gap-3">

        {{-- The person printing (name only) --}}
        <div class="print-signer">
            <div class="print-signer-head">
                <div class="min-w-0">
                    <h3 class="print-signer-title">Person printing</h3>
                    <p class="print-signer-desc">The name of whoever prints the document. Health records already name them at the bottom; turning this on moves the name into the signatures.</p>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <x-ui.input wrapper-class="col-12 col-md-6" name="print_prepared_by_label" id="setting_print_prepared_by_label" label="Caption"
                    :value="$val('print_prepared_by_label')" maxlength="40" placeholder="Prepared by" />
                <div class="col-12 col-md-6">
                    <div class="print-on" role="group" aria-labelledby="print-on-prepared">
                        <span class="print-on-label" id="print-on-prepared">Prints on</span>
                        <x-ui.switch name="print_prepared_by_on_reports" id="setting_print_prepared_by_on_reports" label="Reports" size="md"
                            :checked="(bool) $settings->get('print_prepared_by_on_reports')" />
                        <x-ui.switch name="print_prepared_by_on_health" id="setting_print_prepared_by_on_health" label="Health records" size="md"
                            :checked="(bool) $settings->get('print_prepared_by_on_health')" />
                    </div>
                </div>
            </div>
        </div>

        @for ($n = 1; $n <= PrintBranding::SIGNATORIES; $n++)
            @php
                $p = "print_sig{$n}_";
                $sigPath = (string) $settings->get($p.'image', '');
                $sigUrl = $sigPath !== '' ? $settings->imageUrl($p.'image') : '';
                $sigHeight = max(8, min(30, (int) old($p.'height', $settings->get($p.'height'))));
                $sigName = (string) $val($p.'name');
            @endphp
            <div class="print-signer" data-signer>
                <div class="print-signer-head">
                    <div class="min-w-0">
                        <h3 class="print-signer-title">Signatory {{ $n }}</h3>
                        <p class="print-signer-desc" data-signer-state>{{ $sigName === '' ? 'Not printed until a name is filled in.' : 'Prints as shown.' }}</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-xxl-8">
                        <div class="row g-3">
                            <x-ui.input wrapper-class="col-12 col-md-5" :name="$p.'label'" :id="'setting_'.$p.'label'" label="Caption"
                                :value="$val($p.'label')" maxlength="40" :placeholder="['', 'Prepared by', 'Noted by', 'Approved by'][$n]" data-sig-bind="caption" />
                            <x-ui.input wrapper-class="col-12 col-md-7" :name="$p.'name'" :id="'setting_'.$p.'name'" label="Full name"
                                :value="$sigName" maxlength="100" autocomplete="off" placeholder="For example, Maria L. Santos, RN" data-sig-bind="name" />
                            <x-ui.input wrapper-class="col-12 col-md-7" :name="$p.'position'" :id="'setting_'.$p.'position'" label="Position or title" optional
                                :value="$val($p.'position')" maxlength="100" placeholder="For example, School Nurse" data-sig-bind="position" />
                            <x-ui.input wrapper-class="col-12 col-md-5" :name="$p.'license'" :id="'setting_'.$p.'license'" label="License number" optional
                                :value="$val($p.'license')" maxlength="50" data-sig-bind="license" />

                            <x-ui.field class="col-12 col-md-7" label="Signature image" optional :name="$p.'image'" :for="'setting_'.$p.'image'"
                                help="A transparent PNG of the signature looks best. PNG, JPG or WebP, up to 2 MB.">
                                <div class="print-upload" data-print-image data-saved-src="{{ $sigUrl }}" data-max-bytes="{{ 2 * 1024 * 1024 }}" data-noun="signature">
                                    <input type="file" name="{{ $p }}image" id="setting_{{ $p }}image" accept="{{ $imageAccept }}" data-image-file
                                           @class(['form-control', 'is-invalid' => $errors->has($p.'image')])
                                           @if ($errors->has($p.'image')) aria-invalid="true" aria-describedby="setting_{{ $p }}image-error" @endif>
                                    <input type="hidden" name="remove_{{ $p }}image" value="0" data-image-remove-input>
                                    <div class="print-upload-actions">
                                        <x-ui.button variant="danger" size="sm" icon="trash3" data-image-remove :hidden="$sigUrl === ''">Remove signature</x-ui.button>
                                        <x-ui.button variant="secondary" size="sm" icon="arrow-counterclockwise" data-image-undo hidden>Undo</x-ui.button>
                                        <span class="print-upload-status" data-image-status role="status" aria-live="polite"></span>
                                    </div>
                                </div>
                            </x-ui.field>

                            <x-ui.field class="col-12 col-md-5" label="Signature height" :name="$p.'height'" :for="'setting_'.$p.'height'" help="From 8 to 30 millimetres.">
                                <div class="input-group print-range-number">
                                    <input type="number" name="{{ $p }}height" id="setting_{{ $p }}height" min="8" max="30" step="1" inputmode="numeric"
                                           value="{{ $sigHeight }}" data-sig-height
                                           @class(['form-control', 'is-invalid' => $errors->has($p.'height')])>
                                    <span class="input-group-text">mm</span>
                                </div>
                            </x-ui.field>

                            <div class="col-12">
                                <div class="print-on" role="group" aria-labelledby="print-on-{{ $n }}">
                                    <span class="print-on-label" id="print-on-{{ $n }}">Prints on</span>
                                    <x-ui.switch :name="$p.'on_reports'" :id="'setting_'.$p.'on_reports'" label="Reports" size="md"
                                        :checked="(bool) $settings->get($p.'on_reports')" />
                                    <x-ui.switch :name="$p.'on_health'" :id="'setting_'.$p.'on_health'" label="Health records" size="md"
                                        :checked="(bool) $settings->get($p.'on_health')" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Live preview of this signature, at print size. --}}
                    <div class="col-12 col-xxl-4">
                        <figure @class(['print-sig-preview', 'is-empty' => $sigName === '']) data-sig-preview aria-label="Preview of signatory {{ $n }}">
                            <div class="print-sig-paper">
                                <div class="print-sig-caption" data-sig-out="caption"></div>
                                <div class="print-sig-space">
                                    <img src="{{ $sigUrl }}" alt="Signature preview" data-preview-image style="height: {{ $sigHeight }}mm;" @if ($sigUrl === '') hidden @endif>
                                </div>
                                <div class="print-sig-name" data-sig-out="name"></div>
                                <div class="print-sig-position" data-sig-out="position"></div>
                                <div class="print-sig-license" data-sig-out="license"></div>
                            </div>
                            <figcaption class="print-preview-caption">Shown at print size.</figcaption>
                        </figure>
                    </div>
                </div>
            </div>
        @endfor
    </div>
</x-ui.section>

{{-- ── Footer ───────────────────────────────────────────────────────────── --}}
<x-ui.section title="Footer" description="A short line and page numbers at the bottom of each printed page.">
    <div class="row g-3">
        <x-ui.textarea wrapper-class="col-12" name="print_footer_text" id="setting_print_footer_text" label="Footer text" rows="2"
            :value="$val('print_footer_text')" maxlength="300" :help="$fields['print_footer_text']['help'] ?? null" />

        <div class="col-12">
            <div class="print-matrix" role="group" aria-label="Where the footer prints">
                <div class="print-matrix-row">
                    <span class="print-matrix-label">Footer text</span>
                    <x-ui.switch name="print_footer_on_reports" id="setting_print_footer_on_reports" label="Reports" size="md"
                        :checked="(bool) $settings->get('print_footer_on_reports')" />
                    <x-ui.switch name="print_footer_on_health" id="setting_print_footer_on_health" label="Health records" size="md"
                        :checked="(bool) $settings->get('print_footer_on_health')" />
                </div>
                <div class="print-matrix-row">
                    <span class="print-matrix-label">Page numbers</span>
                    <x-ui.switch name="print_page_numbers_reports" id="setting_print_page_numbers_reports" label="Reports" size="md"
                        :checked="(bool) $settings->get('print_page_numbers_reports')" />
                    <x-ui.switch name="print_page_numbers_health" id="setting_print_page_numbers_health" label="Health records" size="md"
                        :checked="(bool) $settings->get('print_page_numbers_health')" />
                </div>
            </div>
            <p class="form-text mb-0">Page numbers print on downloaded PDFs. When printing from the browser, use the browser's own page number option.</p>
        </div>
    </div>
</x-ui.section>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-print-settings]');
    if (!root) return;

    var CONTENT_MM = parseFloat(root.getAttribute('data-content-width')) || 182;

    function fmt(mm) { return (Math.round(mm * 10) / 10).toString(); }
    function clamp(value, min, max, fallback) {
        var n = parseInt(value, 10);
        if (isNaN(n)) n = fallback;
        return Math.max(min, Math.min(max, n));
    }
    function checkedValue(name, fallback) {
        var el = root.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : fallback;
    }

    // Image pickers (banner and signatures): preview the chosen file, remove, undo.
    root.querySelectorAll('[data-print-image]').forEach(function (wrap) {
        var file = wrap.querySelector('[data-image-file]');
        var removeInput = wrap.querySelector('[data-image-remove-input]');
        var removeBtn = wrap.querySelector('[data-image-remove]');
        var undoBtn = wrap.querySelector('[data-image-undo]');
        var status = wrap.querySelector('[data-image-status]');
        var saved = wrap.getAttribute('data-saved-src') || '';
        var maxBytes = parseInt(wrap.getAttribute('data-max-bytes'), 10) || 0;
        var noun = wrap.getAttribute('data-noun') || 'image';
        var objectUrl = null;

        // The preview this picker drives: the banner page preview, or the signature preview of the same signatory.
        var scope = wrap.closest('[data-signer]') || root;
        var img = scope.querySelector('[data-preview-image]');

        function show(src) {
            if (!img) return;
            if (src) {
                img.hidden = false;
                if (img.getAttribute('src') !== src) img.setAttribute('src', src); else img.dispatchEvent(new Event('load'));
            } else {
                img.hidden = true;
                img.removeAttribute('src');
                img.dispatchEvent(new Event('load'));
            }
        }

        function setState(state, message) {
            removeBtn.hidden = !(state === 'saved' || state === 'new');
            undoBtn.hidden = state !== 'removed';
            status.textContent = message || '';
        }

        file.addEventListener('change', function () {
            var chosen = file.files && file.files[0];
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            removeInput.value = '0';
            if (!chosen) {
                show(saved);
                setState(saved ? 'saved' : 'empty');
                return;
            }
            objectUrl = URL.createObjectURL(chosen);
            show(objectUrl);
            var tooBig = maxBytes && chosen.size > maxBytes;
            setState('new', tooBig
                ? 'This file is larger than ' + Math.round(maxBytes / 1048576) + ' MB and will not be accepted.'
                : 'Not saved yet. Save changes to use this ' + noun + '.');
        });

        removeBtn.addEventListener('click', function () {
            file.value = '';
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            if (saved) {
                removeInput.value = '1';
                show('');
                setState('removed', 'The ' + noun + ' will be removed when you save.');
            } else {
                show('');
                setState('empty');
            }
        });

        undoBtn.addEventListener('click', function () {
            removeInput.value = '0';
            show(saved);
            setState('saved');
        });
    });

    // Letterhead preview: same sizing rule as App\Support\PrintBranding::bannerBox().
    var bannerImg = root.querySelector('[data-paper] [data-preview-image]');
    var bannerWrap = root.querySelector('[data-preview-banner]');
    var fallback = root.querySelector('[data-preview-default]');
    var caption = root.querySelector('[data-preview-caption]');
    var range = root.querySelector('[data-banner-range]');
    var height = root.querySelector('[data-banner-height]');

    function bannerBox(pw, ph, heightMm, fit) {
        var ratio = pw / Math.max(1, ph), w, h;
        if (fit === 'natural') {
            h = heightMm; w = h * ratio;
        } else {
            w = CONTENT_MM; h = w / ratio;
            if (h > heightMm) { h = heightMm; w = h * ratio; }
        }
        if (w > CONTENT_MM) { w = CONTENT_MM; h = w / ratio; }
        return { w: w, h: h };
    }

    function renderBanner() {
        // A saved banner still downloading: wait for its load event instead of flashing the fallback.
        if (bannerImg && !bannerImg.hidden && bannerImg.getAttribute('src') && !bannerImg.complete) return;
        var ready = bannerImg && !bannerImg.hidden && bannerImg.getAttribute('src') && bannerImg.naturalWidth > 0;
        bannerWrap.hidden = !ready;
        fallback.hidden = !!ready;
        if (!ready) {
            caption.textContent = 'No banner. Documents print the logo and clinic name, as shown.';
            return;
        }
        var mm = clamp(height.value, 15, 60, 30);
        var box = bannerBox(bannerImg.naturalWidth, bannerImg.naturalHeight, mm, checkedValue('print_banner_fit', 'width'));
        bannerWrap.style.textAlign = checkedValue('print_banner_align', 'center');
        bannerImg.style.width = (box.w / CONTENT_MM * 100) + '%';
        caption.textContent = 'Prints ' + fmt(box.w) + ' mm wide and ' + fmt(box.h) + ' mm tall on A4 paper.';
    }

    if (bannerImg) {
        bannerImg.addEventListener('load', renderBanner);
        bannerImg.addEventListener('error', function () { bannerImg.hidden = true; renderBanner(); });
    }
    if (range && height) {
        range.addEventListener('input', function () { height.value = range.value; renderBanner(); });
        height.addEventListener('input', function () {
            var n = parseInt(height.value, 10);
            if (!isNaN(n)) range.value = Math.max(15, Math.min(60, n));
            renderBanner();
        });
        height.addEventListener('change', function () { height.value = clamp(height.value, 15, 60, 30); range.value = height.value; renderBanner(); });
    }
    root.querySelectorAll('input[name="print_banner_fit"], input[name="print_banner_align"]').forEach(function (el) {
        el.addEventListener('change', renderBanner);
    });
    renderBanner();

    // Signature previews: caption, image at its printed height, name, position and license.
    root.querySelectorAll('[data-signer]').forEach(function (signer) {
        var preview = signer.querySelector('[data-sig-preview]');
        var state = signer.querySelector('[data-signer-state]');
        var img = signer.querySelector('[data-sig-preview] [data-preview-image]');
        var heightInput = signer.querySelector('[data-sig-height]');
        var inputs = {};
        signer.querySelectorAll('[data-sig-bind]').forEach(function (el) { inputs[el.getAttribute('data-sig-bind')] = el; });

        function text(key) { return inputs[key] ? inputs[key].value.trim() : ''; }

        function render() {
            var caption = text('caption');
            if (caption && !/[:.!?]$/.test(caption)) caption += ':';
            var name = text('name');
            var license = text('license');
            preview.querySelector('[data-sig-out="caption"]').textContent = caption;
            preview.querySelector('[data-sig-out="name"]').textContent = name || 'Name';
            preview.querySelector('[data-sig-out="position"]').textContent = text('position');
            preview.querySelector('[data-sig-out="license"]').textContent = license ? 'License No. ' + license : '';
            preview.classList.toggle('is-empty', name === '');
            state.textContent = name === '' ? 'Not printed until a name is filled in.' : 'Prints as shown.';
            if (img) img.style.height = clamp(heightInput.value, 8, 30, 15) + 'mm';
        }

        Object.keys(inputs).forEach(function (key) { inputs[key].addEventListener('input', render); });
        if (heightInput) heightInput.addEventListener('input', render);
        if (img) img.addEventListener('load', render);
        render();
    });
});
</script>
@endpush
