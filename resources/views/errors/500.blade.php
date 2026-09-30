{{-- Must not touch the database or settings(): see partials/static. --}}
@include('errors.partials.static', [
    'code' => '500',
    'title' => 'Something went wrong',
    'message' => 'The system hit a problem while loading this page. Your saved records are safe. Try again in a moment. If it keeps happening, tell your administrator what you were doing.',
])
