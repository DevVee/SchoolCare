{{--
    Default paginator view (Paginator::defaultView('pagination::sscms')).
    "Showing 1 to 20 of 134 results" + previous / page numbers / next.
    Phones: previous, "Page X of Y", next (44px targets).
    Optional data: noun (e.g. "patients") via $paginator->links(null, ['noun' => 'patients']).
--}}
@php
    $nounText = $noun ?? 'results';
@endphp
@if ($paginator->total() > 0)
<nav class="c-pagination" role="navigation" aria-label="Pagination">
    <p class="c-pagination-info">
        Showing <strong>{{ number_format($paginator->firstItem()) }}</strong> to <strong>{{ number_format($paginator->lastItem()) }}</strong>
        of <strong>{{ number_format($paginator->total()) }}</strong> {{ $nounText }}
    </p>

    @if ($paginator->hasPages())
        <div class="c-pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="btn-action btn-action-neutral disabled" aria-disabled="true" aria-label="Previous page"><i class="bi bi-chevron-left c-icon" aria-hidden="true"></i></span>
            @else
                <a class="btn-action btn-action-neutral" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" title="Previous page"><i class="bi bi-chevron-left c-icon" aria-hidden="true"></i></a>
            @endif

            <ul class="pagination d-none d-sm-flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </ul>

            <span class="page-status d-sm-none">Page <strong>{{ $paginator->currentPage() }}</strong> of {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a class="btn-action btn-action-neutral" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" title="Next page"><i class="bi bi-chevron-right c-icon" aria-hidden="true"></i></a>
            @else
                <span class="btn-action btn-action-neutral disabled" aria-disabled="true" aria-label="Next page"><i class="bi bi-chevron-right c-icon" aria-hidden="true"></i></span>
            @endif
        </div>
    @endif
</nav>
@endif
