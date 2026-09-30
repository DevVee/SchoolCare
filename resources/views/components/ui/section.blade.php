{{--
    x-ui.section: form section. DEFAULT is stacked: title + one-line description on top,
    fields below at full width. Stack several inside one card; a divider is drawn
    between consecutive sections.

    <x-ui.card>
        <x-ui.section title="Identity" description="Name as it appears on school records." columns="3">
            <x-ui.input name="first_name" label="First name" required />
            <x-ui.input name="middle_name" label="Middle name" optional />
            <x-ui.input name="last_name" label="Last name" required />
            <x-ui.textarea wrapper-class="col-full" name="notes" label="Notes" />
        </x-ui.section>
        <x-ui.section title="Contact">
            <div class="row g-3">...</div>                       (or bring your own grid)
        </x-ui.section>
    </x-ui.card>

    columns: 2 | 3 lays direct children out on a responsive grid (1 column on phones,
             2 from sm, 3 from lg when columns=3). Give long fields (textareas, lists,
             choice groups) `class="col-full"` / `wrapper-class="col-full"` to span the row.
    aside:   opt-in legacy layout (title/description left, fields right from lg). Avoid:
             it wastes width.
--}}
@props([
    'title',
    'description' => null,
    'columns' => null,
    'aside' => false,
])
<section {{ $attributes->class(['form-section', 'form-section-aside' => $aside]) }}>
    <div class="form-section-head">
        <h2 class="form-section-title">{{ $title }}</h2>
        @if ($description)<p class="form-section-desc">{{ $description }}</p>@endif
    </div>
    <div @class(['form-section-body', 'form-grid' => $columns, 'form-grid-3' => (int) $columns === 3])>
        {{ $slot }}
    </div>
</section>
