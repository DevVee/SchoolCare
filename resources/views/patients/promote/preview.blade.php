@extends('layouts.app')

@section('title', 'Confirm promotion')

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="'Confirm promotion: '.($categories[$category] ?? $category)"
        description="Check the moves below. All of them are saved together, or none are."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => route('patients.index'), 'Year-end promotion' => route('patients.promote.form', ['category' => $category]), 'Confirm' => null]" />

    @if (! $plan)
        <x-ui.alert variant="warning" title="Nothing to promote">
            No active patients are in the levels you chose to move.
            <x-slot:actions>
                <x-ui.button size="sm" variant="secondary" :href="route('patients.promote.form', ['category' => $category])">Go back</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @else
        <x-ui.card flush title="Moves">
            <x-ui.table caption="Promotion moves">
                <x-slot:head>
                    <x-ui.th>From</x-ui.th><x-ui.th>To</x-ui.th><x-ui.th align="end">Patients</x-ui.th>
                </x-slot:head>
                @foreach ($plan as $step)
                    <tr>
                        <x-ui.td>{{ $categories[$category] ?? $category }}, {{ $step['from'] !== '' ? $step['from'] : 'no year level' }}</x-ui.td>
                        <x-ui.td>
                            <x-ui.icon name="arrow-right" class="text-muted me-1" />{{ $categories[$step['to_category']] ?? $step['to_category'] }}{{ $step['to_level'] !== '' ? ', '.$step['to_level'] : '' }}
                        </x-ui.td>
                        <x-ui.td numeric class="fw-semibold">{{ count($step['ids']) }}</x-ui.td>
                    </tr>
                @endforeach
                <x-slot:foot>
                    <tr><th colspan="2">Total</th><th class="text-end tabular">{{ $total }}</th></tr>
                </x-slot:foot>
            </x-ui.table>
        </x-ui.card>

        <form method="POST" action="{{ route('patients.promote') }}">
            @csrf
            <input type="hidden" name="category" value="{{ $category }}">
            @php $i = 0; @endphp
            @foreach ($mapping as $from => $to)
                <input type="hidden" name="from[{{ $i }}]" value="{{ $from }}">
                <input type="hidden" name="mapping[{{ $i }}]" value="{{ $to }}">
                @php $i++; @endphp
            @endforeach
            <input type="hidden" name="clear_sections" value="{{ $clear ? 1 : 0 }}">

            <x-ui.card>
                <p class="mb-3">{{ $clear ? 'Sections will be cleared for the moved patients.' : 'Sections are kept.' }}
                    Programs and strands are cleared when a patient moves to another category.</p>
                <x-ui.checkbox name="confirm" required :label="'I checked the list. Promote '.$total.' '.\Illuminate\Support\Str::plural('patient', $total).'.'" id="confirmPromote" />
                <x-slot:footer class="justify-content-end">
                    <x-ui.button variant="secondary" :href="route('patients.promote.form', ['category' => $category])">Back</x-ui.button>
                    <x-ui.button type="submit" icon="check2-circle">Promote patients</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>
    @endif
</div>
@endsection
