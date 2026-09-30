{{--
    Ask box (dashboard hero): the assistant as the first thing to speak to (ServiceCo HeroChat).
    Submits to the assistant page with ?q=, which puts the question in the composer and sends it.
    Needs: $aiName, $starters (list of starter questions).
--}}
<form class="c-ask" action="{{ route('ai-assistant.index') }}" method="GET" role="search" data-no-guard>
    <span class="c-ask-ring" aria-hidden="true"></span>
    <x-ui.coco-orb size="sm" class="c-ask-glyph" />
    <label for="dashAsk" class="visually-hidden">Ask {{ $aiName }} anything</label>
    <input id="dashAsk" name="q" type="text" placeholder="Ask {{ $aiName }} anything" autocomplete="off" maxlength="600" required>
    <button type="submit" class="btn btn-primary c-ask-send">
        <span class="d-none d-sm-inline">Ask</span>
        <x-ui.icon name="arrow-up" />
        <span class="visually-hidden d-sm-none">Send</span>
    </button>
</form>
@if (count($starters))
    <div class="c-ask-try">
        <span>Try:</span>
        @foreach ($starters as $starter)
            <a class="c-chip" href="{{ route('ai-assistant.index', ['q' => $starter]) }}">{{ $starter }}</a>
        @endforeach
    </div>
@endif
