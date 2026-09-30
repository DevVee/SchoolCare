{{-- Meta tags + the public website stylesheet. Expects $page, optional $description. --}}
@push('styles')
    <meta name="description" content="{{ $description ?? $page['seo']['description'] }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page['seo']['title'] }}">
    <meta property="og:description" content="{{ $description ?? $page['seo']['description'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($page['hero']['image'])
        <meta property="og:image" content="{{ $page['hero']['image']['url'] }}">
    @endif
    <meta name="theme-color" content="#FFFFFF">
    @vite('resources/scss/landing.scss')
@endpush

@push('scripts')
    @vite('resources/js/landing.js')
@endpush
