{{--
    On / off switch for one row of a Website list. Flipping it sends the PATCH ...toggle form
    (form[data-autosubmit], resources/js/ui/filters.js). The row's action menu offers the same
    change as a menu item, so it also works without JavaScript.
    Expects: $action, $on (bool), $id (unique input id), $name (row name), $onText, $offText.
--}}
<form method="POST" action="{{ $action }}" class="wa-visibility" data-autosubmit>
    @csrf
    @method('PATCH')
    <div class="form-check form-switch form-switch-lg mb-0">
        <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" @checked($on)>
    </div>
    <label for="{{ $id }}" @class(['wa-state', 'is-on' => $on])>{{ $on ? $onText : $offText }}<span class="visually-hidden">: {{ $name }}</span></label>
</form>
