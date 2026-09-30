@extends('layouts.app')

@section('title', 'Year-end promotion')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="Year-end promotion"
        description="Move the active patients of a category to their next year level in one step. You see a preview first."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'Year-end promotion' => null]" />

    <x-ui.card>
        <form method="GET" action="{{ route('patients.promote.form') }}" data-autosubmit>
            <x-ui.section title="1. Choose a category" description="The levels of that category are listed below.">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <x-ui.field label="Category" for="pr-category" class="flex-grow-1" style="max-width: 420px">
                        <select name="category" id="pr-category" class="form-select">
                            <option value="">Select a category</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <noscript><x-ui.button type="submit" variant="secondary">Show levels</x-ui.button></noscript>
                </div>
            </x-ui.section>
        </form>
    </x-ui.card>

    @if ($category)
        <form method="POST" action="{{ route('patients.promote.preview') }}">
            @csrf
            <input type="hidden" name="category" value="{{ $category }}">

            <x-ui.card flush title="2. Where does each level go?"
                subtitle="The highest level defaults to No change. Choose the next category's first level (for example Senior High, Grade 11) or Alumni for graduating students.">
                @if (! $levels)
                    <x-ui.empty-state compact icon="mortarboard" :title="$categories[$category].' has no year levels'"
                        description="Add them in Settings, Academic, or move patients with the bulk actions on the patient list." />
                @else
                    <x-ui.table caption="Promotion plan">
                        <x-slot:head>
                            <x-ui.th>From year level</x-ui.th>
                            <x-ui.th align="end">Active patients</x-ui.th>
                            <x-ui.th width="320px">Move to</x-ui.th>
                        </x-slot:head>
                        {{-- Highest level first, so the order reads like the promotion itself. --}}
                        @foreach (array_reverse($levels) as $i => $level)
                            @php $default = old("mapping.$i", $mapping[$level] ?? ''); @endphp
                            <tr>
                                <x-ui.td class="fw-semibold">
                                    {{ $level !== '' ? $level : 'No year level' }}
                                    <input type="hidden" name="from[{{ $i }}]" value="{{ $level }}">
                                </x-ui.td>
                                <x-ui.td numeric>{{ $counts[$level] ?? 0 }}</x-ui.td>
                                <x-ui.td>
                                    <label class="visually-hidden" for="map-{{ $i }}">Move {{ $level ?: 'patients with no level' }} to</label>
                                    <select name="mapping[{{ $i }}]" id="map-{{ $i }}" class="form-select form-select-sm">
                                        <option value="">No change</option>
                                        @foreach ($targets as $group => $options)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($options as $value => $label)
                                                    <option value="{{ $value }}" @selected($default === $value)>{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
                @if ($levels)
                    <x-slot:footer class="justify-content-between flex-wrap gap-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="clear_sections" value="1" id="clearSections" checked>
                            <label class="form-check-label" for="clearSections">Clear sections (new sections are assigned next school year)</label>
                        </div>
                        <x-ui.button type="submit" icon="eye">Preview promotion</x-ui.button>
                    </x-slot:footer>
                @endif
            </x-ui.card>
        </form>
        <p class="small text-muted mb-0">Inactive and archived patients are not moved.</p>
    @endif
</div>
@endsection
