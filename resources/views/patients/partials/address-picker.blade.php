{{--
    Philippine address picker (PSGC cascade). Behaviour: patients.partials.address-picker-script.
    Props:
      $addrField  : form field name  ('address' | 'guardian_address')
      $addrValue  : current saved value (empty string on create)
      $addrLabel  : label text
      $addrPrefix : unique prefix for DOM ids (e.g. 'pat' | 'grd')
--}}
@php
    $addrValue  = old($addrField, $addrValue ?? '');
    $addrLabel  = $addrLabel ?? 'Address';
    $addrPrefix = $addrPrefix ?? 'addr';
    $addrError  = $errors->has($addrField);
@endphp

<fieldset class="address-picker-wrap" aria-describedby="{{ $addrPrefix }}-help">
    <legend class="form-label">{{ $addrLabel }}</legend>

    @if ($addrValue)
        <p class="small mb-2" id="{{ $addrPrefix }}-existing-alert">
            <span class="text-muted">Saved address:</span>
            <strong id="{{ $addrPrefix }}-existing-text">{{ $addrValue }}</strong>
        </p>
    @endif

    <div class="row g-2">
        <div class="col-12">
            <div class="input-icon">
                <x-ui.icon name="house-door" />
                <input type="text" id="{{ $addrPrefix }}-street" class="form-control" aria-label="House number and street"
                       placeholder="House, block or lot no. and street (optional)"
                       value="{{ $addrValue ? '' : old($addrField . '_street', '') }}">
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <select id="{{ $addrPrefix }}-region" class="form-select" aria-label="Region">
                <option value="">Region</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <select id="{{ $addrPrefix }}-province" class="form-select" aria-label="Province" disabled>
                <option value="">Province</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <select id="{{ $addrPrefix }}-city" class="form-select" aria-label="City or municipality" disabled>
                <option value="">City / Municipality</option>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <select id="{{ $addrPrefix }}-barangay" class="form-select" aria-label="Barangay" disabled>
                <option value="">Barangay</option>
            </select>
        </div>
    </div>

    <div id="{{ $addrPrefix }}-preview" class="form-text d-none">
        <x-ui.icon name="check-circle-fill" class="text-success me-1" />
        <span id="{{ $addrPrefix }}-preview-text"></span>
    </div>
    <div class="form-text" id="{{ $addrPrefix }}-help">
        {{ $addrValue ? 'Pick a new region to barangay to replace the saved address, or leave them blank to keep it.' : 'Pick the region first, then province, city and barangay.' }}
    </div>
    @if ($addrError)
        <div class="invalid-feedback d-block">{{ $errors->first($addrField) }}</div>
    @endif

    {{-- Hidden field submitted with the form --}}
    <input type="hidden" name="{{ $addrField }}" id="{{ $addrPrefix }}-hidden" value="{{ $addrValue }}">
</fieldset>
