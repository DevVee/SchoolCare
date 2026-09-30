{{-- Maintenance mode: no database or settings(). --}}
@include('errors.partials.static', [
    'code' => '503',
    'title' => 'Down for a short update',
    'message' => 'The system is being updated and will be back in a few minutes. Please try again shortly.',
])
