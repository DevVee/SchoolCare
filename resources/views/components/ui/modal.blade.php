{{--
    x-ui.modal: Bootstrap modal. Open with data-bs-toggle="modal" data-bs-target="#id".
    <x-ui.modal id="stockInModal" title="Stock in" size="md">
        (body)
        <x-slot:footer>
            <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" form="stockInForm">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    With a form (body + footer are wrapped in <form>, @csrf and @method added):
    <x-ui.modal id="cancelModal" title="Cancel appointment" :action="route('appointments.cancel', $a)" method="PATCH">
        <x-ui.textarea name="cancelled_reason" label="Reason" required />
        <x-slot:footer>
            <x-ui.button variant="secondary" data-bs-dismiss="modal">Keep appointment</x-ui.button>
            <x-ui.button type="submit" variant="danger">Cancel appointment</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    size: sm 440 | md 520 (default) | lg 640 | xl 800 | fullscreen
--}}
@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'size' => 'md',
    'static' => false,        // backdrop click / Esc do not close
    'scrollable' => true,
    'centered' => true,
    'sheet' => false,         // bottom sheet on phones
    'action' => null,         // wraps body + footer in a form
    'method' => 'POST',
    'files' => false,         // multipart form
    'closeLabel' => 'Close',
])
@php
    $verb = strtoupper($method);
    $sizeClass = match ($size) {
        'sm' => 'modal-sm', 'lg' => 'modal-lg', 'xl' => 'modal-xl', 'fullscreen' => 'modal-fullscreen-sm-down modal-xl', default => 'modal-md',
    };
@endphp
<div {{ $attributes->class(['modal', 'fade', 'modal-sheet' => $sheet]) }} id="{{ $id }}" tabindex="-1" aria-hidden="true"
     @if ($title) aria-labelledby="{{ $id }}-title" @endif
     @if ($static) data-bs-backdrop="static" data-bs-keyboard="false" @endif>
    <div @class(['modal-dialog', $sizeClass, 'modal-dialog-centered' => $centered, 'modal-dialog-scrollable' => $scrollable])>
        <div class="modal-content">
            @if ($action)
                <form method="{{ $verb === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" @if ($files) enctype="multipart/form-data" @endif>
                @if ($verb !== 'GET') @csrf @endif
                @if (! in_array($verb, ['GET', 'POST'], true)) @method($verb) @endif
            @endif
            @if ($title || isset($header))
                <div class="modal-header">
                    @isset($header)
                        {{ $header }}
                    @else
                        <div class="min-w-0">
                            <h2 class="modal-title" id="{{ $id }}-title">{{ $title }}</h2>
                            @if ($subtitle)<p class="modal-subtitle">{{ $subtitle }}</p>@endif
                        </div>
                    @endisset
                    <button type="button" class="btn-close-c" data-bs-dismiss="modal" aria-label="{{ $closeLabel }}"><x-ui.icon name="x-lg" /></button>
                </div>
            @endif
            <div class="modal-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div {{ $footer->attributes->class('modal-footer') }}>{{ $footer }}</div>
            @endisset
            @if ($action)
                </form>
            @endif
        </div>
    </div>
</div>
