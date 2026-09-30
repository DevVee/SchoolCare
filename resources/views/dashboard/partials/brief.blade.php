{{--
    Coco's brief (dashboard hero aside): what is happening in the clinic today, in 3 to 5 lines.
    resources/js/ui/brief.js fetches route('dashboard.brief') after the page shows (the dashboard
    never waits on the AI) and ticks the lines in. Skeleton lines hold the space meanwhile.
    Needs: $aiName.
--}}
<section class="c-brief" data-brief data-brief-url="{{ route('dashboard.brief') }}" aria-labelledby="briefTitle">
    <div class="c-brief-head">
        <x-ui.coco-orb size="xs" still class="c-brief-glyph" />
        <div class="min-w-0">
            <h2 class="c-brief-title" id="briefTitle">{{ $aiName }}'s brief</h2>
            <p class="c-brief-sub" data-brief-meta>Reading today's numbers</p>
        </div>
    </div>
    <ul class="c-brief-lines" data-brief-lines aria-live="polite" aria-busy="true">
        @foreach ([88, 72, 80, 56] as $w)
            <li class="c-brief-line is-loading"><span class="skeleton" style="width: {{ $w }}%"></span></li>
        @endforeach
    </ul>
    <a class="c-brief-more" href="{{ route('ai-assistant.index', ['q' => 'What needs attention today?']) }}">
        Ask a follow-up <x-ui.icon name="arrow-right" />
    </a>
</section>
