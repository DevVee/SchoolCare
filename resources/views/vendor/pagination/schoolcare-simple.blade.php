{{--
    Default simple paginator view (Paginator::defaultSimpleView('pagination::schoolcare-simple')).
    Used by simplePaginate() / cursorPaginate(): "Showing 21 to 40" + Previous / Next.
--}}
@php
    $nounText = $noun ?? 'results';
    $first = method_exists($paginator, 'firstItem') ? $paginator->firstItem() : null;
    $last = method_exists($paginator, 'lastItem') ? $paginator->lastItem() : null;
@endphp
@if ($paginator->count() > 0 || $paginator->hasPages())
<nav class="c-pagination" role="navigation" aria-label="Pagination">
    @if ($first !== null && $last !== null)
        <p class="c-pagination-info">Showing <strong>{{ number_format($first) }}</strong> to <strong>{{ number_format($last) }}</strong> {{ $nounText }}</p>
    @endif

    @if ($paginator->hasPages())
        <div class="c-pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm disabled" aria-disabled="true"><i class="bi bi-chevron-left c-icon" aria-hidden="true"></i>Previous</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="bi bi-chevron-left c-icon" aria-hidden="true"></i>Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next<i class="bi bi-chevron-right c-icon" aria-hidden="true"></i></a>
            @else
                <span class="btn btn-secondary btn-sm disabled" aria-disabled="true">Next<i class="bi bi-chevron-right c-icon" aria-hidden="true"></i></span>
            @endif
        </div>
    @endif
</nav>
@endif
