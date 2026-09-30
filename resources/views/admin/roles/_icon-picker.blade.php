{{--
    Role icon picker. Submits the chosen Bootstrap icon name as `icon`.
    Expects: $currentIcon (string)
--}}
@php
    $icons = [
        ['shield-fill-check', 'Admin'], ['heart-pulse-fill', 'Nurse'], ['person-badge-fill', 'Staff'],
        ['hospital-fill', 'Doctor'], ['capsule', 'Pharmacy'], ['eyedropper', 'Lab'],
        ['clipboard2-pulse-fill', 'Records'], ['box-seam-fill', 'Inventory'], ['chat-dots-fill', 'Reception'],
        ['bar-chart-fill', 'Analyst'], ['gear-fill', 'System'], ['people-fill', 'Users'],
        ['person-fill', 'Person'], ['person-plus-fill', 'Add user'], ['person-check-fill', 'Verified'],
        ['lock-fill', 'Security'], ['shield-lock-fill', 'Shield'], ['shield-fill-exclamation', 'Warning'],
        ['star-fill', 'Star'], ['award-fill', 'Award'], ['patch-check-fill', 'Certified'],
        ['journal-medical', 'Medical'], ['stethoscope', 'Stethoscope'], ['bandaid-fill', 'First aid'],
        ['thermometer-half', 'Temperature'], ['droplet-fill', 'Blood'], ['activity', 'Activity'],
        ['calendar-check-fill', 'Schedule'], ['telephone-fill', 'Phone'], ['envelope-fill', 'Email'],
        ['printer-fill', 'Print'], ['file-earmark-fill', 'Files'], ['graph-up-arrow', 'Reports'],
        ['currency-exchange', 'Finance'], ['truck-flatbed', 'Logistics'], ['tools', 'Tech'],
        ['cpu-fill', 'IT'], ['building-fill-check', 'Facility'], ['mortarboard-fill', 'Education'],
        ['brightness-high-fill', 'General'],
    ];
@endphp
<div class="icon-picker" id="iconPicker">
    <input type="hidden" name="icon" id="iconValue" value="{{ $currentIcon }}">
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <span class="role-icon role-icon-lg" aria-hidden="true"><i class="bi bi-{{ $currentIcon }}" id="previewIconEl"></i></span>
        <div class="flex-grow-1" style="min-width: 200px; max-width: 320px;">
            <label for="iconSearch" class="visually-hidden">Search icons</label>
            <x-ui.input name="icon_search" id="iconSearch" icon="search" size="sm" placeholder="Search icons" autocomplete="off" />
        </div>
    </div>
    <div class="icon-picker-grid" id="iconGrid" role="group" aria-label="Role icon">
        @foreach ($icons as [$icon, $label])
            <button type="button" @class(['icon-option', 'selected' => $currentIcon === $icon])
                    data-icon="{{ $icon }}" data-label="{{ strtolower($label) }}" aria-pressed="{{ $currentIcon === $icon ? 'true' : 'false' }}">
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                <span>{{ $label }}</span>
            </button>
        @endforeach
    </div>
    <p class="text-muted fs-sm mt-2 mb-0" id="iconNoMatch" hidden>No icons match that search.</p>
</div>

@push('scripts')
<script>
(function () {
    function init() {
        var grid = document.getElementById('iconGrid');
        if (!grid) return;
        var value = document.getElementById('iconValue');
        var preview = document.getElementById('previewIconEl');
        var search = document.getElementById('iconSearch');
        var none = document.getElementById('iconNoMatch');

        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('.icon-option');
            if (!btn) return;
            grid.querySelectorAll('.icon-option').forEach(function (o) {
                o.classList.remove('selected');
                o.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('selected');
            btn.setAttribute('aria-pressed', 'true');
            value.value = btn.dataset.icon;
            preview.className = 'bi bi-' + btn.dataset.icon;
        });

        if (search) {
            // The search box is only a helper; never submit it.
            search.removeAttribute('name');
            search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
            search.addEventListener('input', function () {
                var q = this.value.trim().toLowerCase();
                var shown = 0;
                grid.querySelectorAll('.icon-option').forEach(function (el) {
                    var match = !q || el.dataset.icon.indexOf(q) !== -1 || el.dataset.label.indexOf(q) !== -1;
                    el.hidden = !match;
                    if (match) shown++;
                });
                none.hidden = shown > 0;
            });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endpush
