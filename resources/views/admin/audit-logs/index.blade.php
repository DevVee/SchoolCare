@extends('layouts.app')

@section('title', 'Activity log')

@php
    $actionNames = [
        'created' => 'Added', 'updated' => 'Changed', 'deleted' => 'Deleted', 'logged_in' => 'Signed in',
        'logged_out' => 'Signed out', 'exported' => 'Exported', 'approved' => 'Approved', 'cancelled' => 'Cancelled',
        'code_sent' => 'Sign-in code sent', 'code_not_sent' => 'Sign-in code not sent', 'code_verified' => 'Sign-in code accepted',
        'code_failed' => 'Wrong sign-in code', 'code_locked' => 'Sign-in code locked',
    ];
    $actionLabel = fn ($a) => $actionNames[$a] ?? \Illuminate\Support\Str::headline((string) $a);
    $moduleLabel = fn ($m) => \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', (string) $m));

    $userOptions = $users->mapWithKeys(fn ($u) => [(string) $u->id => $u->name])->all();
    $moduleOptions = $modules->mapWithKeys(fn ($m) => [$m => $moduleLabel($m)])->all();
    $actionOptions = $actions->mapWithKeys(fn ($a) => [$a => $actionLabel($a)])->all();
    $filtered = collect(request()->only(['search', 'user_id', 'module', 'action', 'date_from', 'date_to']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Activity log"
        :description="'Who did what, and when. '.number_format($logs->total()).' '.\Illuminate\Support\Str::plural('entry', $logs->total()).($filterUser ? ' by '.$filterUser->name : '').($filtered ? ' match the filters.' : '.')"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Activity log' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" :href="route('admin.audit-logs.export', request()->except('page'))">Download spreadsheet</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filters :action="route('admin.audit-logs.index')" search-placeholder="Search the details"
        :labels="['user_id' => 'User', 'date_from' => 'From', 'date_to' => 'To', 'module' => 'Area', 'action' => 'Action']"
        :options="['user_id' => $userOptions, 'module' => $moduleOptions, 'action' => $actionOptions]">
        <x-slot:inline>
            <x-ui.select name="user_id" size="sm" aria-label="User" :options="$userOptions" placeholder="All users" :selected="request('user_id')" />
        </x-slot:inline>
        <x-ui.input name="date_from" type="date" size="sm" label="From" :value="request('date_from')" />
        <x-ui.input name="date_to" type="date" size="sm" label="To" :value="request('date_to')" />
        <x-ui.select name="module" label="Area" size="sm" :options="$moduleOptions" placeholder="All areas" :selected="request('module')" />
        <x-ui.select name="action" label="Action" size="sm" :options="$actionOptions" placeholder="All actions" :selected="request('action')" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table :paginator="$logs" noun="entries" caption="Activity log" responsive="stack">
            <x-slot:head>
                <x-ui.th data-phone-sub>When</x-ui.th>
                <x-ui.th data-phone-sub>User</x-ui.th>
                <x-ui.th>Action</x-ui.th>
                <x-ui.th priority="lg">Area</x-ui.th>
                <x-ui.th priority="md" data-phone-title>Details</x-ui.th>
                <x-ui.th priority="xl">Signed in from</x-ui.th>
                <x-ui.th align="end"><span class="visually-hidden">Changes</span></x-ui.th>
            </x-slot:head>

            @foreach ($logs as $log)
                <tr>
                    <x-ui.td label="When" muted>
                        <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y, g:i:s A') }}">{{ $log->created_at->format('M j, Y, g:i A') }}</time>
                    </x-ui.td>
                    <x-ui.td identity>
                        <span class="cell-title d-block">{{ $log->user_name ?? 'System' }}</span>
                        @if ($log->user)<span class="cell-sub d-block cell-truncate" title="{{ $log->user->email }}">{{ $log->user->email }}</span>@endif
                    </x-ui.td>
                    <x-ui.td label="Action"><x-ui.status-badge :status="$log->action" type="audit" :label="$actionLabel($log->action)" /></x-ui.td>
                    <x-ui.td priority="lg" label="Area">{{ $moduleLabel($log->module) }}</x-ui.td>
                    <x-ui.td priority="md" label="Details" truncate>{{ $log->description }}</x-ui.td>
                    <x-ui.td priority="xl" label="Signed in from" muted>{{ $log->ip_address ?? '-' }}</x-ui.td>
                    <x-ui.td actions>
                        @if ($log->old_values || $log->new_values)
                            <x-ui.button variant="ghost" size="sm" data-bs-toggle="modal" data-bs-target="#detailModal"
                                data-old="{{ json_encode($log->old_values) }}" data-new="{{ json_encode($log->new_values) }}"
                                data-desc="{{ $log->description }}"
                                :aria-label="'View changes: '.\Illuminate\Support\Str::limit($log->description, 60)">View changes</x-ui.button>
                        @endif
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($filtered)
                    <x-ui.empty-state icon="search" title="No activity matches these filters" description="Try a different search or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.audit-logs.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="clock-history" title="No activity yet" description="Sign-ins and changes will be listed here." compact />
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>

<x-ui.modal id="detailModal" title="What changed" size="lg">
    <p class="text-ink-2 mb-3" id="modalDesc"></p>
    <div class="table-responsive">
        <table class="table table-c table-sm align-middle mb-0">
            <thead><tr><th scope="col">Item</th><th scope="col">Before</th><th scope="col">After</th></tr></thead>
            <tbody id="changesBody"></tbody>
        </table>
    </div>
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Close</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('detailModal');
    if (!modal) return;

    function parse(raw) {
        try { return JSON.parse(raw || 'null'); } catch (e) { return null; }
    }
    function show(v) {
        if (v === null || v === undefined || v === '') return '-';
        if (v === true) return 'Yes';
        if (v === false) return 'No';
        if (Array.isArray(v)) return v.length ? v.map(show).join(', ') : '-';
        if (typeof v === 'object') return Object.keys(v).map(function (k) { return label(k) + ': ' + show(v[k]); }).join('; ');
        return String(v);
    }
    function label(key) {
        return String(key).replace(/[_-]+/g, ' ').replace(/^\w/, function (c) { return c.toUpperCase(); });
    }
    function cell(text, cls) {
        var td = document.createElement('td');
        td.textContent = text;
        td.className = 'cell-wrap' + (cls ? ' ' + cls : '');
        return td;
    }

    modal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        if (!btn) return;
        var before = parse(btn.dataset.old) || {};
        var after = parse(btn.dataset.new) || {};
        if (typeof before !== 'object') before = { value: before };
        if (typeof after !== 'object') after = { value: after };
        document.getElementById('modalDesc').textContent = btn.dataset.desc || '';
        var body = document.getElementById('changesBody');
        body.innerHTML = '';
        var keys = Object.keys(before).concat(Object.keys(after)).filter(function (k, i, all) { return all.indexOf(k) === i; });
        if (!keys.length) {
            var tr = document.createElement('tr');
            var td = cell('No details were recorded.', 'text-muted');
            td.colSpan = 3;
            tr.appendChild(td);
            body.appendChild(tr);
            return;
        }
        keys.forEach(function (k) {
            var tr = document.createElement('tr');
            var th = document.createElement('th');
            th.scope = 'row';
            th.className = 'fw-semibold';
            th.textContent = label(k);
            tr.appendChild(th);
            tr.appendChild(cell(k in before ? show(before[k]) : '-', 'text-ink-2'));
            tr.appendChild(cell(k in after ? show(after[k]) : '-'));
            body.appendChild(tr);
        });
    });
});
</script>
@endpush
