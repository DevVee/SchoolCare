{{--
    Meta tags + the public website stylesheet and script.
    Expects $page. Optional: $title and $description (default: the clinic's SEO
    text from Administration > Website), $image (og:image URL).
--}}
@push('styles')
    <meta name="description" content="{{ $description ?? $page['seo']['description'] }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? $page['seo']['title'] }}">
    <meta property="og:description" content="{{ $description ?? $page['seo']['description'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if (! empty($image))
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="theme-color" content="#FFFFFF">
    @vite('resources/scss/landing.scss')
@endpush

@push('scripts')
    @vite('resources/js/landing.js')
@endpush
