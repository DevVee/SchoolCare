{{--
    Permission matrix: modules (rows) by View / Create / Edit / Delete / Other.

    Expects:
      $modules              config('permissions.modules')
      $columns              config('permissions.columns')
      $existingPermissions  permission names present in the database
      $selected             permission names currently checked
      $readonly             (optional) show ticks instead of checkboxes, e.g. for the full-access role
--}}
@php
    $readonly = $readonly ?? false;
    $selected = collect($selected ?? [])->all();
    $exists   = array_flip($existingPermissions ?? []);
@endphp

<x-ui.card flush id="permissionMatrix" title="Permissions"
    :subtitle="$readonly ? 'This role can do everything below. It cannot be changed.' : 'Tick what this role may do. Choosing Create, Edit, Delete or an extra action also ticks View for that area.'">
    @unless ($readonly)
        <x-slot:actions>
            <x-ui.button variant="secondary" size="sm" icon="check2-all" data-matrix-all="1">Select all</x-ui.button>
            <x-ui.button variant="ghost" size="sm" data-matrix-all="0">Clear all</x-ui.button>
        </x-slot:actions>
    @endunless

    <x-ui.table sticky min-width="760px" caption="Permissions by area" class="permission-matrix" :hover="false" data-no-cards>
        <x-slot:head>
            <x-ui.th>Area</x-ui.th>
            @foreach ($columns as $colKey => $colLabel)
                <x-ui.th align="center" width="96px">
                    <span class="d-block">{{ $colLabel }}</span>
                    @unless ($readonly)
                        <label class="matrix-toggle" title="Select all {{ $colLabel }}">
                            <input type="checkbox" class="form-check-input matrix-col-toggle" data-col="{{ $colKey }}"
                                   aria-label="Select all: {{ $colLabel }}">
                            <span class="matrix-toggle-text">All</span>
                        </label>
                    @endunless
                </x-ui.th>
            @endforeach
            <x-ui.th>
                <span class="d-block">Other actions</span>
                @unless ($readonly)
                    <label class="matrix-toggle" title="Select all other actions">
                        <input type="checkbox" class="form-check-input matrix-col-toggle" data-col="other" aria-label="Select all other actions">
                        <span class="matrix-toggle-text">All</span>
                    </label>
                @endunless
            </x-ui.th>
        </x-slot:head>

        @foreach ($modules as $moduleKey => $module)
            <tr data-row="{{ $moduleKey }}">
                <x-ui.td>
                    @if ($readonly)
                        <span class="d-flex align-items-center gap-2">
                            <x-ui.icon :name="$module['icon'] ?? 'grid'" class="text-muted" />
                            <span class="fw-semibold text-ink">{{ $module['label'] }}</span>
                        </span>
                    @else
                        <label class="matrix-row-label">
                            <input type="checkbox" class="form-check-input matrix-row-toggle" data-row="{{ $moduleKey }}"
                                   aria-label="Select everything in {{ $module['label'] }}" title="Select everything in {{ $module['label'] }}">
                            <x-ui.icon :name="$module['icon'] ?? 'grid'" class="text-muted" />
                            <span class="fw-semibold text-ink">{{ $module['label'] }}</span>
                        </label>
                    @endif
                </x-ui.td>

                @foreach ($columns as $colKey => $colLabel)
                    @php $perm = $module['actions'][$colKey] ?? null; @endphp
                    <x-ui.td align="center">
                        @if ($perm && isset($exists[$perm]))
                            @if ($readonly)
                                @if (in_array($perm, $selected, true))
                                    <x-ui.icon name="check-lg" class="text-success" :label="$module['label'].': '.$colLabel.' allowed'" />
                                @else
                                    <span class="text-muted" aria-label="Not allowed">-</span>
                                @endif
                            @else
                                <label class="matrix-cell" title="{{ $module['label'] }}: {{ $colLabel }}">
                                    <input type="checkbox"
                                           class="form-check-input matrix-perm"
                                           name="permissions[]"
                                           value="{{ $perm }}"
                                           id="perm_{{ $perm }}"
                                           data-row="{{ $moduleKey }}"
                                           data-col="{{ $colKey }}"
                                           aria-label="{{ $module['label'] }}: {{ $colLabel }}"
                                           @checked(in_array($perm, $selected, true))>
                                </label>
                            @endif
                        @else
                            <span class="text-muted" aria-label="Does not apply">-</span>
                        @endif
                    </x-ui.td>
                @endforeach

                <x-ui.td wrap>
                    @php $others = array_filter($module['other'] ?? [], fn ($label, $perm) => isset($exists[$perm]), ARRAY_FILTER_USE_BOTH); @endphp
                    @forelse ($others as $perm => $label)
                        @if ($readonly)
                            <span class="d-inline-flex align-items-center gap-1 me-3">
                                <x-ui.icon name="check-lg" class="text-success" />{{ $label }}
                            </span>
                        @else
                            <div class="form-check c-check mb-0">
                                <input type="checkbox"
                                       class="form-check-input matrix-perm"
                                       name="permissions[]"
                                       value="{{ $perm }}"
                                       id="perm_{{ $perm }}"
                                       data-row="{{ $moduleKey }}"
                                       data-col="other"
                                       @checked(in_array($perm, $selected, true))>
                                <label class="form-check-label" for="perm_{{ $perm }}">{{ $label }}</label>
                            </div>
                        @endif
                    @empty
                        <span class="text-muted">-</span>
                    @endforelse
                </x-ui.td>
            </tr>
        @endforeach
    </x-ui.table>
</x-ui.card>

@unless ($readonly)
@push('scripts')
<script>
(function () {
    function init() {
        var root = document.getElementById('permissionMatrix');
        if (!root) return;

        var perms = function () { return Array.prototype.slice.call(root.querySelectorAll('.matrix-perm')); };
        var byRow = function (row) { return perms().filter(function (cb) { return cb.dataset.row === row; }); };
        var byCol = function (col) { return perms().filter(function (cb) { return cb.dataset.col === col; }); };

        function setToggleState(toggle, boxes) {
            var on = boxes.filter(function (cb) { return cb.checked; }).length;
            toggle.checked = boxes.length > 0 && on === boxes.length;
            toggle.indeterminate = on > 0 && on < boxes.length;
            toggle.disabled = boxes.length === 0;
        }

        function refresh() {
            root.querySelectorAll('.matrix-row-toggle').forEach(function (t) { setToggleState(t, byRow(t.dataset.row)); });
            root.querySelectorAll('.matrix-col-toggle').forEach(function (t) { setToggleState(t, byCol(t.dataset.col)); });
        }

        root.addEventListener('change', function (e) {
            var t = e.target;
            if (t.classList.contains('matrix-row-toggle')) {
                byRow(t.dataset.row).forEach(function (cb) { cb.checked = t.checked; });
            } else if (t.classList.contains('matrix-col-toggle')) {
                byCol(t.dataset.col).forEach(function (cb) { cb.checked = t.checked; });
                // Any action on an area implies being able to view it.
                if (t.checked && t.dataset.col !== 'view') {
                    byCol(t.dataset.col).forEach(function (cb) {
                        var view = byRow(cb.dataset.row).find(function (v) { return v.dataset.col === 'view'; });
                        if (view) view.checked = true;
                    });
                }
            } else if (t.classList.contains('matrix-perm') && t.checked && t.dataset.col !== 'view') {
                var view = byRow(t.dataset.row).find(function (cb) { return cb.dataset.col === 'view'; });
                if (view) view.checked = true;
            }
            refresh();
        });

        root.querySelectorAll('[data-matrix-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var on = this.dataset.matrixAll === '1';
                perms().forEach(function (cb) { cb.checked = on; });
                refresh();
            });
        });

        refresh();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endpush
@endunless
