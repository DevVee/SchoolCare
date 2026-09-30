{{--
    PSGC address picker behaviour for patients.partials.address-picker.
    Include once per page, after the pickers (prefixes 'pat' and 'grd').
--}}
<script>
/* ── Philippine Address Picker (PSGC Cloud API) ──────────────────────────── */
(function () {
    const BASE = 'https://psgc.cloud/api';

    function buildPicker(prefix) {
        const sel = id => document.getElementById(prefix + '-' + id);
        const regionSel   = sel('region');
        const provinceSel = sel('province');
        const citySel     = sel('city');
        const barangaySel = sel('barangay');
        const streetIn    = sel('street');
        const hiddenIn    = sel('hidden');
        const preview     = sel('preview');
        const previewTxt  = sel('preview-text');

        if (!regionSel) return;   // picker not on this page

        // ── helpers ────────────────────────────────────────────────────────
        function resetSelect(el, placeholder) {
            el.innerHTML = `<option value="">${placeholder}</option>`;
            el.disabled = true;
        }

        function populate(el, items, placeholder) {
            el.innerHTML = `<option value="">${placeholder}</option>`;
            items.sort((a, b) => a.name.localeCompare(b.name))
                 .forEach(item => {
                     const opt = document.createElement('option');
                     opt.value = item.code;
                     opt.dataset.name = item.name;
                     opt.textContent = item.name;
                     el.appendChild(opt);
                 });
            el.disabled = false;
        }

        async function apiFetch(path) {
            try {
                const r = await fetch(BASE + path);
                if (!r.ok) throw new Error(r.status);
                return await r.json();
            } catch (e) {
                console.warn('PSGC API error:', path, e);
                return [];
            }
        }

        function setLoading(el, msg) {
            el.innerHTML = `<option value="">${msg}</option>`;
            el.disabled = true;
        }

        function compose() {
            const street   = (streetIn?.value || '').trim();
            const brgyOpt  = barangaySel.selectedOptions[0];
            const cityOpt  = citySel.selectedOptions[0];
            const provOpt  = provinceSel.selectedOptions[0];
            const regOpt   = regionSel.selectedOptions[0];

            const brgy  = brgyOpt?.dataset.name  || '';
            const city  = cityOpt?.dataset.name  || '';
            const prov  = provOpt?.dataset.name  || '';
            const reg   = regOpt?.dataset.name   || '';

            if (!brgy && !city) {
                // User hasn't selected anything , keep existing value untouched
                if (preview) preview.classList.add('d-none');
                return;
            }

            const parts = [street, brgy ? 'Brgy. ' + brgy : '', city, prov, reg]
                .filter(Boolean).join(', ');

            hiddenIn.value = parts;
            if (previewTxt) previewTxt.textContent = parts;
            if (preview) preview.classList.remove('d-none');
        }

        // ── Load regions on init ───────────────────────────────────────────
        (async () => {
            setLoading(regionSel, 'Loading regions…');
            regionSel.disabled = false;
            const regions = await apiFetch('/regions');
            populate(regionSel, regions, 'Region');
        })();

        // ── Region → Provinces ─────────────────────────────────────────────
        regionSel.addEventListener('change', async function () {
            resetSelect(provinceSel, 'Province');
            resetSelect(citySel,     'City / Municipality');
            resetSelect(barangaySel, 'Barangay');
            if (!this.value) { compose(); return; }

            setLoading(provinceSel, 'Loading provinces…');
            const data = await apiFetch(`/regions/${this.value}/provinces`);
            populate(provinceSel, data, 'Province');
            compose();
        });

        // ── Province → Cities + Municipalities ────────────────────────────
        provinceSel.addEventListener('change', async function () {
            resetSelect(citySel,     'City / Municipality');
            resetSelect(barangaySel, 'Barangay');
            if (!this.value) { compose(); return; }

            setLoading(citySel, 'Loading cities…');
            const [cities, munis] = await Promise.all([
                apiFetch(`/provinces/${this.value}/cities`),
                apiFetch(`/provinces/${this.value}/municipalities`),
            ]);
            populate(citySel, [...cities, ...munis], 'City / Municipality');
            compose();
        });

        // ── City/Municipality → Barangays ─────────────────────────────────
        citySel.addEventListener('change', async function () {
            resetSelect(barangaySel, 'Barangay');
            if (!this.value) { compose(); return; }

            const opt      = this.selectedOptions[0];
            const isCity   = opt?.textContent?.toLowerCase().includes('city') ||
                             opt?.textContent?.toLowerCase().startsWith('city');

            setLoading(barangaySel, 'Loading barangays…');

            // Try city endpoint first; fall back to municipality
            let data = await apiFetch(`/cities/${this.value}/barangays`);
            if (!data.length) {
                data = await apiFetch(`/municipalities/${this.value}/barangays`);
            }
            populate(barangaySel, data, 'Barangay');
            compose();
        });

        // ── Barangay / Street change → compose ────────────────────────────
        barangaySel.addEventListener('change', compose);
        if (streetIn) streetIn.addEventListener('input', compose);
    }

    // Init both pickers
    buildPicker('pat');
    buildPicker('grd');
})();
/* ── End Address Picker ──────────────────────────────────────────────────── */
</script>
