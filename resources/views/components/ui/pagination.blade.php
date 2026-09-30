{{--
    x-ui.pagination: "Showing 1 to 20 of 134 patients" + page links (keeps the query string).
    <x-ui.pagination :paginator="$patients" noun="patients" />
    x-ui.table renders this for you when given :paginator. Legacy {{ $x->links() }} calls use the
    same markup (Paginator::defaultView is set to pagination::schoolcare in AppServiceProvider).
--}}
@props([
    'paginator',
    'noun' => null,
    'onEachSide' => 1,
])
@if ($paginator)
    @php
        $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
        if (method_exists($paginator, 'withQueryString')) {
            $paginator->withQueryString();
        }
        if ($isLengthAware && method_exists($paginator, 'onEachSide')) {
            $paginator->onEachSide($onEachSide);
        }
    @endphp
    {{ $paginator->links($isLengthAware ? 'pagination::schoolcare' : 'pagination::schoolcare-simple', ['noun' => $noun]) }}
@endif
