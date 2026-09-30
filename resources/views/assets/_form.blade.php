{{-- Asset form sections, placed inside an x-ui.card by create/edit. Expects $asset (new or existing) and $categories. --}}
@php
    $conditions = \App\Models\Asset::conditions();
    if ($asset->condition && ! in_array($asset->condition, $conditions, true)) {
        $conditions[] = $asset->condition;
    }
    $conditionOptions = collect($conditions)->mapWithKeys(fn ($c) => [$c => $c])->all();
@endphp
<x-ui.section title="Asset" description="What it is, how many there are and where to find it.">
    <div class="row g-3">
        <x-ui.input wrapper-class="col-12 col-md-8" name="name" label="Name" maxlength="200" required
            placeholder="For example: Digital BP monitor" :value="$asset->name" />
        <x-ui.input wrapper-class="col-12 col-md-4" name="quantity" type="number" label="Quantity" min="0" required :value="$asset->quantity" />
        <x-ui.input wrapper-class="col-12 col-sm-6" name="category" label="Category" maxlength="100" list="categoryOptions" optional
            placeholder="For example: Diagnostic, Furniture, First aid" :value="$asset->category" />
        <datalist id="categoryOptions">
            @foreach ($categories as $c)<option value="{{ $c }}"></option>@endforeach
        </datalist>
        <x-ui.input wrapper-class="col-12 col-sm-6" name="property_number" label="Property or serial number" maxlength="100" class="font-monospace"
            optional :value="$asset->property_number" />
        <x-ui.select wrapper-class="col-12 col-sm-6" name="condition" label="Condition" :options="$conditionOptions" required :selected="$asset->condition" />
        <x-ui.input wrapper-class="col-12 col-sm-6" name="location" label="Location" maxlength="150" optional
            placeholder="For example: Clinic room 1, storage cabinet" :value="$asset->location" />
    </div>
</x-ui.section>

<x-ui.section title="Purchase" description="Used to compute the recorded value of clinic assets.">
    <div class="row g-3">
        <x-ui.input wrapper-class="col-12 col-sm-6" name="acquired_at" type="date" label="Date acquired" optional
            max="{{ today()->toDateString() }}" :value="$asset->acquired_at" />
        <x-ui.input wrapper-class="col-12 col-sm-6" name="cost" type="number" label="Cost per unit" min="0" step="0.01" optional :value="$asset->cost" />
        <x-ui.textarea wrapper-class="col-12" name="notes" label="Notes" rows="3" maxlength="2000" optional
            placeholder="Model, maintenance history, who is responsible" :value="$asset->notes" />
    </div>
</x-ui.section>

<x-ui.section title="Picture" description="A photo helps staff find the right item.">
    <div class="vstack gap-3">
        @if ($asset->image_url)
            <img src="{{ $asset->image_url }}" alt="Current picture of {{ $asset->name }}" class="img-fluid rounded-3 border" style="max-width: 240px;" loading="lazy">
            <x-ui.checkbox name="remove_image" label="Remove current picture" unchecked-value="0" />
        @endif
        <x-ui.input name="image" type="file" accept="image/jpeg,image/png,image/webp"
            :label="$asset->image_url ? 'Replace picture' : 'Upload a picture'" help="JPG, PNG or WebP, up to 2 MB." />
    </div>
</x-ui.section>
