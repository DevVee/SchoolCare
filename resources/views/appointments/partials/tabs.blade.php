{{-- Appointments module views: [List | Calendar | Today] --}}
<x-ui.tabs label="Appointment views" :active="$active ?? 'list'" :items="[
    'list'     => ['label' => 'List', 'href' => route('appointments.index'), 'icon' => 'list-ul'],
    'calendar' => ['label' => 'Calendar', 'href' => route('appointments.calendar'), 'icon' => 'calendar3'],
    'today'    => ['label' => 'Today', 'href' => route('appointments.today'), 'icon' => 'calendar-day'],
]" />
