@extends('layouts.app')
@use('App\Support\DisplayFormat')

@section('title', 'Health forms')

@php
    $statusItems = [];
    foreach (\App\Models\PatientIntakeSubmission::STATUSES as $value => $label) {
        $statusItems[$value] = ['label' => $label, 'count' => $counts[$value] ?? 0];
    }
    $searching = filled(request('search'));
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Health forms" class="no-print"
        description="Health information sent by students and parents. Nothing is added to patient records until you approve it."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'Health forms' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="printer" onclick="window.print()">Print QR poster</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="no-print">
        @include('patients.partials.tabs', ['active' => 'intake', 'pendingForms' => $counts['pending'] ?? 0])
    </div>

    <div class="row g-3">
        <div class="col-lg-8 no-print vstack gap-3">
            <x-ui.filters :action="route('patients.intake.index')" search-placeholder="Name or student ID" :keep="['status']">
                <x-slot:pills>
                    <x-ui.tabs :items="$statusItems" :active="$status" param="status" label="Form status" />
                </x-slot:pills>
            </x-ui.filters>

            <x-ui.card flush>
                <x-ui.table :paginator="$submissions" noun="forms" caption="Health forms" responsive="stack">
                    <x-slot:head>
                        <x-ui.th>Name</x-ui.th>
                        <x-ui.th priority="md">Category</x-ui.th>
                        <x-ui.th priority="lg">Student ID</x-ui.th>
                        <x-ui.th>{{ $status === 'pending' ? 'Sent' : 'Reviewed' }}</x-ui.th>
                        <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                    </x-slot:head>
                    @foreach ($submissions as $s)
                        <tr>
                            <x-ui.td identity>
                                <div class="identity">
                                    <x-ui.avatar :name="$s->full_name" size="sm" />
                                    <div class="identity-text">
                                        <a href="{{ route('patients.intake.show', $s) }}" class="identity-title">{{ $s->full_name }}</a>
                                        @if ($s->birthdate)<span class="identity-sub">Born {{ DisplayFormat::date($s->birthdate) }}</span>@endif
                                    </div>
                                </div>
                            </x-ui.td>
                            <x-ui.td priority="md" label="Category">{{ $categoryLabels[$s->payload['category'] ?? ''] ?? ($s->payload['category'] ?? '-') }}</x-ui.td>
                            <x-ui.td priority="lg" label="Student ID" class="tabular">{{ $s->student_id ?: '-' }}</x-ui.td>
                            <x-ui.td :label="$status === 'pending' ? 'Sent' : 'Reviewed'" muted>
                                @if ($status === 'pending')
                                    <time datetime="{{ $s->created_at->toIso8601String() }}" title="{{ DisplayFormat::date($s->created_at) }}">{{ $s->created_at->diffForHumans() }}</time>
                                @else
                                    {{ DisplayFormat::date($s->reviewed_at) }}@if ($s->reviewedBy), {{ $s->reviewedBy->name }}@endif
                                @endif
                            </x-ui.td>
                            <x-ui.td actions>
                                <x-ui.action-menu :for="$s->full_name">
                                    <x-ui.action-menu.item :href="route('patients.intake.show', $s)" :icon="$status === 'pending' ? 'clipboard2-check' : 'eye'">{{ $status === 'pending' ? 'Review' : 'View' }}</x-ui.action-menu.item>
                                </x-ui.action-menu>
                            </x-ui.td>
                        </tr>
                    @endforeach
                    <x-slot:empty>
                        @if ($searching)
                            <x-ui.empty-state icon="search" title="No forms match this search" compact>
                                <x-ui.button variant="secondary" size="sm" :href="route('patients.intake.index', ['status' => $status])">Clear search</x-ui.button>
                            </x-ui.empty-state>
                        @else
                            <x-ui.empty-state module="patients" icon="clipboard2-heart" compact
                                :title="$status === 'pending' ? 'No forms waiting for review' : 'Nothing here yet'"
                                :description="$status === 'pending' ? 'Share the link or QR poster so families can send their health information.' : null" />
                        @endif
                    </x-slot:empty>
                </x-ui.table>
            </x-ui.card>
        </div>

        {{-- Public link + printable QR code --}}
        <div class="col-lg-4">
            <x-ui.card class="qr-card" title="Share the form" module="patients" icon="qr-code">
                @unless ($enabled)
                    <x-ui.alert variant="warning" class="mb-3 no-print">
                        The public form is turned off, so this link shows "not found".
                        @can('manage-settings')
                            <a href="{{ route('admin.settings.edit', 'intake') }}">Turn it on in Settings</a>.
                        @endcan
                    </x-ui.alert>
                @endunless
                <div class="text-center">
                    <div class="print-only mb-2">
                        <div class="h5 mb-0">{{ settings('clinic_name') ?: settings('app_name') }}</div>
                        @if (trim((string) settings('org_name', '')) !== '')<div>{{ settings('org_name') }}</div>@endif
                        <div class="h4 mt-3">Student Health Information Form</div>
                        <p>Scan the code with your phone camera to fill in the form.</p>
                    </div>
                    <div id="qrCode" class="qr-box" role="img" aria-label="QR code for the health information form link"
                         data-url="{{ $publicUrl }}"></div>
                    <p class="small mt-2 mb-0"><a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="text-break">{{ $publicUrl }}</a></p>
                </div>
                <x-slot:footer class="justify-content-center no-print">
                    <x-ui.button size="sm" variant="secondary" icon="clipboard" id="copyLink">Copy link</x-ui.button>
                    <x-ui.button size="sm" icon="printer" onclick="window.print()">Print poster</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.qr-box { display: inline-block; padding: .5rem; background: #fff; border: 1px solid var(--c-border, #E2E8F0); border-radius: 8px; min-width: 180px; min-height: 180px; }
.print-only { display: none; }
@media print {
    .no-print, footer { display: none !important; }
    .print-only { display: block; }
    .qr-card { border: none !important; box-shadow: none !important; margin-top: 30mm; }
    .qr-card .card-header { display: none !important; }
    .qr-card #qrCode { border: none !important; }
    .qr-card #qrCode svg { width: 90mm !important; height: 90mm !important; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
(function () {
    const box = document.getElementById('qrCode');
    if (box && window.qrcode) {
        const qr = qrcode(0, 'M');
        qr.addData(box.dataset.url);
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
        const svg = box.querySelector('svg');
        if (svg) { svg.setAttribute('width', '180'); svg.setAttribute('height', '180'); svg.setAttribute('aria-hidden', 'true'); }
    } else if (box) {
        box.textContent = 'QR code could not load. Use the link below.';
    }
    const copy = document.getElementById('copyLink');
    if (copy) copy.addEventListener('click', () => {
        navigator.clipboard.writeText(box.dataset.url).then(
            () => window.toast ? window.toast('Link copied.') : null,
            () => window.toast ? window.toast('Copy failed. Select the link and copy it.', 'error') : null
        );
    });
})();
</script>
@endpush
