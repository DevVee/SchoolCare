{{--
    Position number with move up / move down buttons for one row of a Website list.
    Expects: $position, $action (the PATCH ...move route), $name (row name for the button labels), $first, $last.
--}}
<div class="wa-order">
    <span class="wa-order-num tabular" aria-hidden="true">{{ $position }}</span>
    <form method="POST" action="{{ $action }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="direction" value="up">
        <x-ui.button type="submit" variant="ghost" size="sm" icon="chevron-up" icon-only :label="'Move '.$name.' up'" :disabled="$first" />
    </form>
    <form method="POST" action="{{ $action }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="direction" value="down">
        <x-ui.button type="submit" variant="ghost" size="sm" icon="chevron-down" icon-only :label="'Move '.$name.' down'" :disabled="$last" />
    </form>
</div>
