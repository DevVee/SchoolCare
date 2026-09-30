@extends('layouts.app')

@section('title', 'UI kit')

@push('styles')
    <x-ui.brand-style />
@endpush

@php
    // ---- Demo data (this page is a living style guide; nothing here touches the database) ----
    $demoPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
        range(1, 10), 134, 10, max(1, (int) request('page', 3)),
        ['path' => request()->url(), 'query' => request()->query()]
    );
    $demoErrors = new \Illuminate\Support\MessageBag([
        'demo_email' => ['Enter a valid email address, for example nurse@school.edu.'],
        'demo_category' => ['Choose a patient category.'],
        'demo_notes' => ['Notes must be at most 500 characters.'],
    ]);
    $errors->put('demo', $demoErrors);

    $patients = [
        ['name' => 'Maria Santos', 'no' => 'P-2026-0012', 'category' => 'Student', 'grade' => 'Grade 10, Rizal', 'sex' => 'Female', 'age' => 16, 'contact' => '0917 555 0123', 'status' => 'active', 'visit' => 'returned_to_class'],
        ['name' => 'Jose Reyes', 'no' => 'P-2026-0047', 'category' => 'Student', 'grade' => 'Grade 7, Mabini', 'sex' => 'Male', 'age' => 12, 'contact' => '0918 555 0199', 'status' => 'active', 'visit' => 'sent_home'],
        ['name' => 'Ana Dela Cruz', 'no' => 'P-2026-0101', 'category' => 'Teaching staff', 'grade' => 'Science Dept.', 'sex' => 'Female', 'age' => 34, 'contact' => '0920 555 0147', 'status' => 'inactive', 'visit' => 'rest_in_clinic'],
        ['name' => 'Carlo Mendoza', 'no' => 'P-2026-0133', 'category' => 'Student', 'grade' => 'Grade 12, Bonifacio', 'sex' => 'Male', 'age' => 18, 'contact' => '', 'status' => 'active', 'visit' => 'referred_to_hospital'],
    ];
    $users = [
        ['name' => 'Joy Ramos', 'email' => 'joy.ramos@school.edu', 'role' => 'administrator', 'active' => true, 'login' => 'Sep 26, 2026, 8:02 AM'],
        ['name' => 'Paolo Lim', 'email' => 'paolo.lim@school.edu', 'role' => 'doctor', 'active' => true, 'login' => 'Sep 25, 2026, 3:47 PM'],
        ['name' => 'Liza Garcia', 'email' => 'liza.garcia.records.office@school.edu', 'role' => 'staff', 'active' => false, 'login' => 'Aug 30, 2026, 10:15 AM'],
    ];
    $categories = ['student' => 'Student', 'teaching' => 'Teaching staff', 'non_teaching' => 'Non-teaching staff'];
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
@endphp

@section('content')
<div class="vstack gap-4">

    <x-ui.page-header title="UI kit" description="Every x-ui component in its variants. Use this page to check spacing, states and colours after design changes."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Settings' => null, 'UI kit' => null]">
        Brand colour and fonts come from <code>x-ui.brand-style</code>.
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="book" href="#buttons">Jump to buttons</x-ui.button>
            <x-ui.button icon="plus-lg" data-bs-toggle="modal" data-bs-target="#demoModalMd">Open modal</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ========================================================== Logo --}}
    <x-ui.card title="Logo" subtitle="Driven by Admin settings: app name, tagline, school name, logos, sidebar title mode.">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <x-ui.logo />
            <x-ui.logo variant="sidebar" />
            <x-ui.logo variant="topbar" />
            <x-ui.logo variant="auth" />
            <x-ui.logo :wordmark="false" size="28" />
            <div class="bg-brand p-3 rounded-2"><x-ui.logo variant="auth" inverse /></div>
        </div>
    </x-ui.card>

    {{-- ========================================================== Module tones --}}
    <x-ui.card title="Module tones" subtitle="One colour per module: icon glyph plus a soft tint chip. Used by sidebar icons, stat strips, widget headers and empty states (never beside page titles)." module="overview">
        <div class="d-flex flex-wrap gap-3">
            @foreach (config('ui.module_meta') as $key => $meta)
                <div class="d-flex align-items-center gap-2" style="min-width: 11rem">
                    <x-ui.icon-chip :module="$key" />
                    <span class="small"><span class="fw-semibold">{{ $meta['label'] }}</span><br><code class="small">{{ $key }}</code></span>
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
            <x-ui.icon-chip module="patients" size="sm" /><x-ui.icon-chip module="patients" /><x-ui.icon-chip module="patients" size="lg" />
            <x-ui.icon-chip tone="warning" icon="exclamation-triangle" />
            <span class="tone-medicines"><x-ui.icon name="capsule" /> glyph only (.tone-medicines)</span>
        </div>
    </x-ui.card>

    {{-- ========================================================== Hero --}}
    <x-ui.hero subtitle="Clinic visits, patients and medicine stock at a glance.">
        <x-slot:actions>
            <x-ui.button variant="hero" icon="journal-plus" href="#">Log a visit</x-ui.button>
            <x-ui.button variant="hero-outline" icon="calendar-check" href="#">Appointments</x-ui.button>
        </x-slot:actions>
    </x-ui.hero>

    {{-- ========================================================== Stat cards --}}
    <section aria-labelledby="stats-heading">
        <h2 id="stats-heading" class="h5 mb-3">Stat cards</h2>
        <x-ui.stat-cards cols="5">
            <x-ui.stat-card label="Total patients" value="1284" tone="patients" href="#">
                <span class="stat-mark mark-up">+12</span> added this month
            </x-ui.stat-card>
            <x-ui.stat-card label="Staff on duty" value="4" icon="person-badge" tone="teal">
                <span class="stat-meta-item"><span class="stat-dot tone-green"></span>Active 3</span>
                <span class="stat-meta-item"><span class="stat-dot tone-amber"></span>Leave 1</span>
            </x-ui.stat-card>
            <x-ui.stat-card label="Visits today" value="24" tone="logbook" sub="8:00 AM to now" />
            <x-ui.stat-card label="Appointments" value="7" tone="appointments" delta="-2" sub="vs yesterday" />
            <x-ui.stat-card label="Gender split" value="58" icon="people" tone="amber">
                <span class="stat-meta-item"><x-ui.icon name="gender-male" class="tone-sky" /> 30 male</span>
                <span class="stat-meta-item"><x-ui.icon name="gender-female" class="tone-rose" /> 28 female</span>
            </x-ui.stat-card>
        </x-ui.stat-cards>

        <p class="overline mb-2">Array shortcut (x-ui.stat-strip renders the same cards)</p>
        <x-ui.stat-strip :items="[
            ['label' => 'Visits today', 'value' => 24, 'module' => 'logbook', 'href' => '#'],
            ['label' => 'In clinic now', 'value' => 3, 'tone' => 'teal', 'icon' => 'door-open'],
            ['label' => 'Pending appointments', 'value' => 5, 'module' => 'appointments', 'hint' => '2 for today'],
            ['label' => 'Low stock items', 'value' => 6, 'tone' => 'warning', 'icon' => 'exclamation-triangle', 'hint' => 'Below reorder level'],
        ]" />

        <p class="overline mb-2">Tones</p>
        <x-ui.stat-cards cols="6">
            @foreach (['brand', 'rose', 'amber', 'teal', 'cyan', 'indigo', 'sky', 'green', 'emerald', 'violet', 'purple', 'slate'] as $tone)
                <x-ui.stat-card :label="$tone" :value="$loop->iteration * 7" icon="circle-half" :tone="$tone" />
            @endforeach
        </x-ui.stat-cards>
    </section>

    {{-- ========================================================== Buttons --}}
    <x-ui.card title="Buttons" subtitle="Solid primary, 6px radius, press feedback. 44px targets on touch screens." id="buttons">
        <div class="vstack gap-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <x-ui.button icon="plus-lg">Primary</x-ui.button>
                <x-ui.button variant="secondary" icon="arrow-left">Secondary</x-ui.button>
                <x-ui.button variant="outline" icon="download">Outline</x-ui.button>
                <x-ui.button variant="ghost" icon="printer">Ghost</x-ui.button>
                <x-ui.button variant="danger" icon="trash">Danger</x-ui.button>
                <x-ui.button variant="success" icon="check-lg">Success</x-ui.button>
                <x-ui.button variant="link">Link button</x-ui.button>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <x-ui.button size="lg">Large</x-ui.button>
                <x-ui.button>Medium</x-ui.button>
                <x-ui.button size="sm">Small</x-ui.button>
                <x-ui.button size="xs" variant="secondary">Extra small</x-ui.button>
                <x-ui.button icon-right="arrow-right" variant="secondary">Icon right</x-ui.button>
                <x-ui.button icon="pencil" icon-only label="Edit" variant="secondary" />
                <x-ui.button icon="three-dots" icon-only label="More" variant="ghost" size="sm" />
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <x-ui.button loading>Saving</x-ui.button>
                <x-ui.button variant="secondary" loading>Loading</x-ui.button>
                <x-ui.button disabled>Disabled</x-ui.button>
                <x-ui.button variant="secondary" href="#" disabled>Disabled link</x-ui.button>
                <x-ui.button href="{{ route('dashboard') }}" icon="house">Link as button</x-ui.button>
            </div>
            <div>
                <p class="overline mb-2">Legacy classes (un-migrated views)</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary">btn-primary</button>
                    <button type="button" class="btn btn-outline-primary">btn-outline-primary</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm">btn-outline-secondary btn-sm</button>
                    <button type="button" class="btn btn-outline-danger btn-xs">btn-xs</button>
                    <span class="badge bg-primary">badge bg-primary</span>
                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill">subtle badge</span>
                    <a href="#" class="text-primary">text-primary link</a>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- ========================================================== Badges --}}
    <x-ui.card title="Badges and status" subtitle="4px tags with a status dot. Accepts tones and Bootstrap colour names.">
        <div class="vstack gap-3">
            <div class="d-flex flex-wrap gap-2">
                @foreach (['brand', 'success', 'warning', 'danger', 'info', 'neutral', 'orange', 'teal', 'cobi'] as $tone)
                    <x-ui.badge :color="$tone">{{ ucfirst($tone) }}</x-ui.badge>
                @endforeach
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach (['brand', 'success', 'warning', 'danger', 'info', 'neutral'] as $tone)
                    <x-ui.badge :color="$tone" variant="solid">{{ ucfirst($tone) }}</x-ui.badge>
                @endforeach
                <x-ui.badge color="primary" variant="outline">Outline</x-ui.badge>
                <x-ui.badge color="danger" icon="exclamation-triangle-fill">With icon</x-ui.badge>
                <x-ui.badge color="info" size="sm">Small</x-ui.badge>
                <x-ui.badge color="bg-success" :dot="false">No dot</x-ui.badge>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach (['pending', 'approved', 'completed', 'cancelled', 'no_show', 'active', 'inactive', 'sent', 'failed', 'skipped', 'expired', 'low_stock'] as $status)
                    <x-ui.status-badge :status="$status" />
                @endforeach
            </div>
            <div class="d-flex flex-wrap gap-2">
                <x-ui.status-badge status="referred_to_hospital" type="disposition" />
                <x-ui.status-badge status="stock_in" type="inventory" />
                <x-ui.status-badge :status="true" type="patient" />
                <x-ui.status-badge :status="false" type="patient" />
                <x-ui.status-badge status="administrator" type="role" />
                <x-ui.status-badge status="mild" type="severity" />
                <x-ui.status-badge status="moderate" type="severity" />
                <x-ui.status-badge status="severe" type="severity" />
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach (['rose', 'amber', 'teal', 'cyan', 'indigo', 'sky', 'green', 'emerald', 'violet', 'purple', 'slate', 'logbook', 'medicines'] as $tone)
                    <x-ui.badge :color="$tone">{{ ucfirst($tone) }}</x-ui.badge>
                @endforeach
            </div>
        </div>
    </x-ui.card>

    {{-- ========================================================== Forms --}}
    <x-ui.card title="Form controls" subtitle="Labels above controls, helper text, inline errors, required markers, old() input.">
        <form method="GET" action="{{ route('ui.kit') }}" onsubmit="return false">
            <x-ui.section title="Identity" description="Stacked section with a 3-column field grid (columns=&quot;3&quot;)." columns="3">
                <x-ui.input name="demo_first_name" label="First name" required placeholder="Maria" />
                <x-ui.input name="demo_middle_name" label="Middle name" optional />
                <x-ui.input name="demo_last_name" label="Last name" required />
                <x-ui.input name="demo_birthdate" type="date" label="Birthdate" help="Used to compute age on reports." />
                <x-ui.select name="demo_sex" label="Sex" :options="['female' => 'Female', 'male' => 'Male']" placeholder="Select" />
                <x-ui.textarea wrapper-class="col-full" name="demo_address" label="Address" rows="2" optional />
            </x-ui.section>
            <x-ui.section title="Validation" description="Errors render inline, next to the field, and replace the helper text.">
                <div class="row g-3">
                    <x-ui.input wrapper-class="col-12 col-sm-6" name="demo_email" type="email" label="Email" bag="demo" value="nurse@" help="Hidden while there is an error." />
                    <x-ui.select wrapper-class="col-12 col-sm-6" name="demo_category" label="Category" :options="$categories" placeholder="Choose a category" bag="demo" required />
                    <x-ui.textarea wrapper-class="col-12" name="demo_notes" label="Notes" rows="3" bag="demo" />
                </div>
            </x-ui.section>
            <x-ui.section title="Choices" description="Checkboxes, radios and switches. The whole row is a 44px target on touch screens.">
                <div class="row g-3">
                    <div class="col-12 col-md-6 vstack gap-1">
                        <x-ui.checkbox name="demo_pwd" label="Person with disability" description="Shown on the health card." />
                        <x-ui.checkbox name="demo_roles[]" value="nurse" label="Nurse" checked />
                        <x-ui.checkbox name="demo_level" type="radio" value="jhs" label="Junior high school" checked />
                        <x-ui.checkbox name="demo_level" type="radio" value="shs" label="Senior high school" />
                    </div>
                    <div class="col-12 col-md-6 vstack gap-2">
                        <x-ui.switch name="demo_sms" label="Send SMS to guardian" description="Guardians get a text when a visit is logged." checked />
                        <x-ui.switch name="demo_email_alerts" label="Email alerts" />
                        <x-ui.switch name="demo_disabled" label="Disabled switch" disabled />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-ui.input name="demo_search_icon" label="Input with icon" icon="telephone" placeholder="0917 555 0123" />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-ui.field label="Search input" for="search-demo_q">
                            <x-ui.search-input name="demo_q" value="Maria" placeholder="Name, patient no. or contact" />
                        </x-ui.field>
                    </div>
                    <div class="col-12 col-md-6">
                        <x-ui.input name="demo_small" size="sm" label="Small input" placeholder="Small" />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-ui.input name="demo_readonly" label="Read only" value="P-2026-0012" readonly />
                    </div>
                </div>
            </x-ui.section>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <x-ui.button variant="secondary" icon="arrow-left">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check-lg">Save patient</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- ========================================================== Filters + table (scroll) --}}
    <section aria-labelledby="table-heading" class="vstack gap-3">
        <h2 id="table-heading" class="h5 mb-0">Filters and tables</h2>

        <x-ui.filters :action="route('ui.kit')" search-placeholder="Name, patient no. or contact"
:labels="['date_from' => 'From', 'date_to' => 'To', 'category' => 'Category', 'status' => 'Status']"
            :options="['category' => $categories, 'status' => ['active' => 'Active', 'inactive' => 'Inactive']]">
            <x-slot:inline>
                <x-ui.date-range from-name="date_from" to-name="date_to" :max="now()" />
            </x-slot:inline>
            <x-ui.select name="category" label="Category" size="sm" :options="$categories" placeholder="All categories" :selected="request('category')" />
            <x-ui.select name="status" label="Status" size="sm" :options="['active' => 'Active', 'inactive' => 'Inactive']" placeholder="Any status" :selected="request('status')" />
            <x-slot:pills>
                <a href="#" class="filter-pill active">All <span class="count">134</span></a>
                <a href="#" class="filter-pill"><span class="dot bg-warning"></span>Pending <span class="count">5</span></a>
            </x-slot:pills>
            <x-slot:stats>
                <span><i class="dot bg-success"></i>120 active</span>
                <span><i class="dot bg-secondary"></i>14 inactive</span>
            </x-slot:stats>
            <x-slot:actions>
                <x-ui.dropdown label="Export" icon="download" variant="secondary">
                    <x-ui.dropdown-item href="#" icon="filetype-pdf">PDF</x-ui.dropdown-item>
                    <x-ui.dropdown-item href="#" icon="filetype-xlsx">Excel</x-ui.dropdown-item>
                </x-ui.dropdown>
            </x-slot:actions>
        </x-ui.filters>

        <x-ui.card flush title="Scroll mode" subtitle="Low-priority columns hide on smaller screens; the table scrolls inside its card.">
            <x-ui.table :paginator="$demoPaginator" noun="patients" sticky caption="Patients (scroll mode)">
                <x-slot:head>
                    <x-ui.th sortable="name">Patient</x-ui.th>
                    <x-ui.th priority="md">Patient no.</x-ui.th>
                    <x-ui.th priority="lg">Category</x-ui.th>
                    <x-ui.th priority="xl">Sex</x-ui.th>
                    <x-ui.th priority="lg" sortable="age" align="end">Age</x-ui.th>
                    <x-ui.th priority="xl">Contact</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                </x-slot:head>
                @foreach ($patients as $p)
                    <tr>
                        <x-ui.td identity>
                            <div class="identity">
                                <x-ui.avatar :name="$p['name']" size="sm" />
                                <div class="identity-text">
                                    <a href="#" class="identity-title">{{ $p['name'] }}</a>
                                    <span class="identity-sub">{{ $p['grade'] }}<span class="d-md-none">, {{ $p['no'] }}</span></span>
                                </div>
                            </div>
                        </x-ui.td>
                        <x-ui.td priority="md" label="Patient no." muted>{{ $p['no'] }}</x-ui.td>
                        <x-ui.td priority="lg" label="Category">{{ $p['category'] }}</x-ui.td>
                        <x-ui.td priority="xl" label="Sex">{{ $p['sex'] }}</x-ui.td>
                        <x-ui.td priority="lg" label="Age" numeric>{{ $p['age'] }}</x-ui.td>
                        <x-ui.td priority="xl" label="Contact">{{ $p['contact'] ?: 'Not recorded' }}</x-ui.td>
                        <x-ui.td label="Status"><x-ui.status-badge :status="$p['status']" type="patient" /></x-ui.td>
                        <x-ui.td actions>
                            <x-ui.action-menu :for="$p['name']">
                                <x-ui.action-menu.item href="#" icon="eye">View</x-ui.action-menu.item>
                                <x-ui.action-menu.item href="#" icon="pencil">Edit</x-ui.action-menu.item>
                                <x-ui.action-menu.item href="#" icon="printer">Print health card</x-ui.action-menu.item>
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item :action="route('ui.kit')" method="GET" icon="trash" danger
                                    confirm="This removes {{ $p['name'] }} from the patient list. Visit history is kept."
                                    confirm-title="Delete patient?" confirm-button="Delete patient">Delete</x-ui.action-menu.item>
                            </x-ui.action-menu>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.card flush title="Stack mode" subtitle="Below 576px each row becomes a card with label and value pairs.">
            <x-ui.table responsive="stack" dense caption="Today's clinic log (stack mode)">
                <x-slot:head>
                    <x-ui.th>Patient</x-ui.th>
                    <x-ui.th priority="md">Time in</x-ui.th>
                    <x-ui.th priority="lg">Complaint</x-ui.th>
                    <x-ui.th>Disposition</x-ui.th>
                    <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                </x-slot:head>
                @foreach ($patients as $i => $p)
                    <tr>
                        <x-ui.td identity>
                            <span class="cell-title d-block">{{ $p['name'] }}</span>
                            <span class="cell-sub d-block">{{ $p['grade'] }}</span>
                        </x-ui.td>
                        <x-ui.td priority="md" label="Time in">{{ ['8:05 AM', '9:40 AM', '10:15 AM', '1:20 PM'][$i] }}</x-ui.td>
                        <x-ui.td priority="lg" label="Complaint" wrap>{{ ['Headache and mild fever', 'Stomach ache after lunch', 'Dizziness', 'Sprained ankle during PE class'][$i] }}</x-ui.td>
                        <x-ui.td label="Disposition"><x-ui.status-badge :status="$p['visit']" type="disposition" /></x-ui.td>
                        <x-ui.td actions>
                            <x-ui.action-menu :for="$p['name']">
                                <x-ui.action-menu.item href="#" icon="eye">View visit</x-ui.action-menu.item>
                                <x-ui.action-menu.item href="#" icon="chat-dots">Send SMS to guardian</x-ui.action-menu.item>
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item icon="trash" danger confirm="The visit entry will be removed." confirm-title="Remove visit?" confirm-button="Remove">Remove</x-ui.action-menu.item>
                            </x-ui.action-menu>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.card flush title="Users" subtitle="Row actions: one ghost more button, text items, destructive last. Rows stay on one line.">
            <x-ui.table caption="Users">
                <x-slot:head>
                    <x-ui.th sortable="name">Name</x-ui.th>
                    <x-ui.th>Role</x-ui.th>
                    <x-ui.th priority="md">Status</x-ui.th>
                    <x-ui.th priority="lg" sortable="last_login">Last sign in</x-ui.th>
                    <x-ui.th align="end"><span class="visually-hidden">Actions</span></x-ui.th>
                </x-slot:head>
                @foreach ($users as $u)
                    <tr>
                        <x-ui.td identity>
                            <div class="identity">
                                <x-ui.avatar :name="$u['name']" size="sm" />
                                <div class="identity-text">
                                    <a href="#" class="identity-title">{{ $u['name'] }}</a>
                                    <span class="identity-sub cell-truncate" title="{{ $u['email'] }}">{{ $u['email'] }}</span>
                                </div>
                            </div>
                        </x-ui.td>
                        <x-ui.td label="Role"><x-ui.status-badge :status="$u['role']" type="role" :dot="false" /></x-ui.td>
                        <x-ui.td priority="md" label="Status"><x-ui.status-badge :status="$u['active']" type="patient" /></x-ui.td>
                        <x-ui.td priority="lg" label="Last sign in" muted>{{ $u['login'] }}</x-ui.td>
                        <x-ui.td actions>
                            <x-ui.action-menu :for="$u['name']">
                                <x-ui.action-menu.item href="#" icon="eye">View</x-ui.action-menu.item>
                                <x-ui.action-menu.item href="#" icon="pencil">Edit</x-ui.action-menu.item>
                                <x-ui.action-menu.item icon="key" confirm="They will get an email with a link to set a new password." :confirm-title="'Reset password for '.$u['name'].'?'" confirm-button="Send reset link">Reset password</x-ui.action-menu.item>
                                @if ($u['active'])
                                    <x-ui.action-menu.item icon="person-dash" confirm="They will not be able to sign in until reactivated." :confirm-title="'Deactivate '.$u['name'].'?'" confirm-button="Deactivate">Deactivate</x-ui.action-menu.item>
                                @else
                                    <x-ui.action-menu.item icon="person-check" confirm="They will be able to sign in again." :confirm-title="'Activate '.$u['name'].'?'" confirm-button="Activate">Activate</x-ui.action-menu.item>
                                @endif
                                <x-ui.action-menu.divider />
                                <x-ui.action-menu.item icon="trash" danger confirm="This permanently removes the account. Their activity stays in the audit log." :confirm-title="'Delete '.$u['name'].'?'" confirm-button="Delete user">Delete</x-ui.action-menu.item>
                            </x-ui.action-menu>
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.card flush title="Empty table">
            <x-ui.table>
                <x-slot:head>
                    <x-ui.th>Medicine</x-ui.th>
                    <x-ui.th>Stock</x-ui.th>
                </x-slot:head>
                <x-slot:empty>
                    <x-ui.empty-state icon="search" title="No medicines match these filters" description="Try a different name or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('ui.kit')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                </x-slot:empty>
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="Pagination (legacy ->links())" subtitle="Every existing ->links() call now renders this view.">
            {{ $demoPaginator->links() }}
        </x-ui.card>
    </section>

    {{-- ========================================================== Charts --}}
    <section aria-labelledby="charts-heading">
        <h2 id="charts-heading" class="h5 mb-3">Charts</h2>
        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <x-ui.card>
                    <x-ui.chart type="bar" title="Visits per day" subtitle="This week"
                        :series="[['name' => 'Visits', 'data' => [18, 24, 15, 22, 27, 6, 2]]]" :categories="$days" />
                </x-ui.card>
            </div>
            <div class="col-12 col-xl-6">
                <x-ui.card>
                    <x-ui.chart type="line" title="Visits and consultations" subtitle="Monthly, this school year"
                        :series="[
                            ['name' => 'Visits', 'data' => [210, 245, 198, 260, 150, 90, 220, 280, 265]],
                            ['name' => 'Consultations', 'data' => [80, 95, 70, 110, 60, 30, 85, 120, 102]],
                        ]" :categories="$months" />
                </x-ui.card>
            </div>
            <div class="col-12 col-xl-6">
                <x-ui.card>
                    <x-ui.chart type="area" title="Medicines dispensed" subtitle="Units per month"
                        :series="[['name' => 'Units', 'data' => [320, 410, 380, 450, 300, 120, 390, 470, 430]]]" :categories="$months" />
                </x-ui.card>
            </div>
            <div class="col-12 col-xl-6">
                <x-ui.card>
                    <x-ui.chart type="stacked-bar" title="Visits by level" subtitle="This week"
                        :series="[
                            ['name' => 'Junior high', 'data' => [10, 14, 8, 12, 15, 3, 1]],
                            ['name' => 'Senior high', 'data' => [6, 7, 5, 8, 9, 2, 1]],
                            ['name' => 'Staff', 'data' => [2, 3, 2, 2, 3, 1, 0]],
                        ]" :categories="$days" />
                </x-ui.card>
            </div>
            <div class="col-12 col-xl-6">
                <x-ui.card>
                    <x-ui.chart type="horizontal-bar" title="Top complaints" subtitle="Last 30 days"
                        :series="[['name' => 'Cases', 'data' => [42, 31, 25, 18, 12]]]"
                        :categories="['Headache', 'Stomach ache', 'Fever', 'Minor wound', 'Dizziness']" />
                </x-ui.card>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <x-ui.card>
                    <x-ui.chart type="donut" title="Visit outcome" :series="['Returned to class' => 142, 'Sent home' => 31, 'Rest in clinic' => 22, 'Referred' => 5]" height="300" />
                </x-ui.card>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <x-ui.card>
                    <x-ui.chart type="bar" title="Referrals" :series="[['name' => 'Referrals', 'data' => [0, 0, 0]]]" :categories="['Jul', 'Aug', 'Sep']" empty="No referrals this quarter." />
                </x-ui.card>
            </div>
        </div>
    </section>

    {{-- ========================================================== Tabs --}}
    <x-ui.card title="Tabs" subtitle="Link based and deep-linkable (?tab=...).">
        <div class="vstack gap-3">
            <x-ui.tabs :items="[
                'all' => ['label' => 'All', 'count' => 134],
                'pending' => ['label' => 'Pending', 'count' => 5, 'icon' => 'hourglass-split'],
                'approved' => ['label' => 'Approved', 'count' => 12],
                'completed' => 'Completed',
            ]" param="tab" />
            <x-ui.tabs variant="underline" :items="[
                'profile' => ['label' => 'Profile', 'icon' => 'person'],
                'visits' => ['label' => 'Visits', 'count' => 8],
                'consultations' => 'Consultations',
                'documents' => 'Documents',
            ]" param="section" />
        </div>
    </x-ui.card>

    {{-- ========================================================== Section nav --}}
    <x-ui.card title="Section nav" subtitle="Sticky list on desktop, horizontal tabs on phones. Used by Settings.">
        <div class="row g-4">
            <div class="col-lg-3">
                <x-ui.section-nav title="Settings" active="branding" :items="[
                    'general' => ['label' => 'General', 'icon' => 'sliders'],
                    'branding' => ['label' => 'Branding', 'icon' => 'palette'],
                    'academic' => ['label' => 'School year', 'icon' => 'mortarboard'],
                    'sms' => ['label' => 'Text messages', 'icon' => 'chat-dots', 'count' => 2],
                    'email' => ['label' => 'Email', 'icon' => 'envelope'],
                ]" />
            </div>
            <div class="col-lg-9">
                <x-ui.alert variant="neutral" :icon="false">Section content goes here. On desktop the list on the left stays in view while this column scrolls.</x-ui.alert>
            </div>
        </div>
    </x-ui.card>

    {{-- ========================================================== Feedback --}}
    <x-ui.card title="Alerts, toasts and dialogs">
        <div class="vstack gap-2">
            <x-ui.alert variant="info" title="Clinic hours changed">The clinic now opens 7:30 AM to 4:30 PM on weekdays.</x-ui.alert>
            <x-ui.alert variant="success" dismissible>Patient record saved.</x-ui.alert>
            <x-ui.alert variant="warning" title="Low stock" accent>
                6 medicines are below their reorder level.
                <x-slot:actions><x-ui.button size="sm" variant="secondary" href="#">Review stock</x-ui.button></x-slot:actions>
            </x-ui.alert>
            <x-ui.alert variant="danger" title="Please fix the errors below">
                <ul><li>Enter a valid email address.</li><li>Choose a patient category.</li></ul>
            </x-ui.alert>
            <x-ui.alert variant="neutral" :icon="false">Neutral alert without an icon.</x-ui.alert>
        </div>

        <p class="overline mt-4 mb-2">Toasts (window.toast)</p>
        <div class="d-flex flex-wrap gap-2">
            <x-ui.button variant="secondary" size="sm" data-demo-toast="success" data-message="Patient record saved.">Success toast</x-ui.button>
            <x-ui.button variant="secondary" size="sm" data-demo-toast="info" data-message="SMS queued for 3 guardians.">Info toast</x-ui.button>
            <x-ui.button variant="secondary" size="sm" data-demo-toast="warning" data-message="Paracetamol is running low.">Warning toast</x-ui.button>
            <x-ui.button variant="secondary" size="sm" data-demo-toast="error" data-message="Could not send the SMS. Check the SMS settings.">Error toast (stays)</x-ui.button>
        </div>

        <p class="overline mt-4 mb-2">Confirm dialog ([data-confirm] and window.confirmDialog)</p>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('ui.kit') }}" data-confirm="This removes the record permanently. Visit history is kept."
                  data-confirm-title="Delete patient?" data-confirm-variant="danger" data-confirm-button="Delete patient">
                <input type="hidden" name="confirmed" value="delete">
                <x-ui.button type="submit" variant="danger" icon="trash">Delete (form)</x-ui.button>
            </form>
            <x-ui.button variant="secondary" icon="send" data-confirm="24 guardians will receive a reminder." data-confirm-title="Send reminders now?" data-confirm-button="Send reminders">Send reminders (button)</x-ui.button>
            <a href="{{ route('ui.kit') }}?confirmed=export" class="btn btn-secondary" data-confirm="Exports can take a minute for large date ranges." data-confirm-title="Export all records?" data-confirm-button="Export">Export (link)</a>
            <x-ui.button variant="secondary" icon="x-circle" data-confirm="The guardian will be notified." data-confirm-title="Cancel appointment?" data-confirm-variant="warning"
                data-confirm-input="cancelled_reason" data-confirm-input-label="Reason for cancelling" data-confirm-button="Cancel appointment">With reason input</x-ui.button>
            <x-ui.button variant="secondary" id="demoConfirmJs">JS promise API</x-ui.button>
        </div>
        @if (request('confirmed'))
            <x-ui.alert variant="success" class="mt-3">Confirmed action: {{ request('confirmed') }}</x-ui.alert>
        @endif

        <p class="overline mt-4 mb-2">Modals</p>
        <div class="d-flex flex-wrap gap-2">
            @foreach (['sm' => 'Small (440)', 'md' => 'Medium (520)', 'lg' => 'Large (640)', 'xl' => 'Extra large (800)'] as $size => $text)
                <x-ui.button variant="secondary" size="sm" data-bs-toggle="modal" data-bs-target="#demoModal{{ ucfirst($size) }}">{{ $text }}</x-ui.button>
            @endforeach
            <x-ui.button variant="secondary" size="sm" data-bs-toggle="modal" data-bs-target="#demoModalForm">Modal with form</x-ui.button>
        </div>
    </x-ui.card>

    {{-- ========================================================== Dropdowns --}}
    <x-ui.card title="Dropdowns and action menus">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <x-ui.dropdown label="Options" align="start">
                <x-ui.dropdown-header>Patient</x-ui.dropdown-header>
                <x-ui.dropdown-item href="#" icon="eye">View profile</x-ui.dropdown-item>
                <x-ui.dropdown-item href="#" icon="pencil" meta="E">Edit</x-ui.dropdown-item>
                <x-ui.dropdown-item href="#" icon="printer" active>Print health card</x-ui.dropdown-item>
                <x-ui.dropdown-divider />
                <x-ui.dropdown-item icon="trash" tone="danger" confirm="This cannot be undone." confirm-title="Delete patient?">Delete</x-ui.dropdown-item>
            </x-ui.dropdown>
            <div class="d-flex align-items-center gap-2"><span class="text-muted small">Row action menu:</span>
                <x-ui.action-menu for="Maria Santos">
                    <x-ui.action-menu.item href="#" icon="eye">View</x-ui.action-menu.item>
                    <x-ui.action-menu.item href="#" icon="pencil">Edit</x-ui.action-menu.item>
                    <x-ui.action-menu.divider />
                    <x-ui.action-menu.item icon="trash" danger confirm="This cannot be undone." confirm-title="Delete patient?" confirm-button="Delete">Delete</x-ui.action-menu.item>
                </x-ui.action-menu>
            </div>
            <div class="d-flex align-items-center gap-2"><span class="text-muted small">Counts:</span>
                <x-ui.count :value="7" /><x-ui.count :value="148" /><x-ui.count :value="3" urgent label="3 overdue" />
            </div>
        </div>
    </x-ui.card>

    {{-- ========================================================== Detail + misc --}}
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <x-ui.card title="Description list" subtitle="Show pages">
                <x-ui.description-list :items="[
                    'Patient no.' => 'P-2026-0012',
                    'Category' => 'Student',
                    'Grade and section' => 'Grade 10, Rizal',
                    'Blood type' => null,
                ]">
                    <x-ui.description-item label="Status"><x-ui.status-badge status="active" /></x-ui.description-item>
                    <x-ui.description-item label="Allergies"></x-ui.description-item>
                </x-ui.description-list>
            </x-ui.card>
        </div>
        <div class="col-12 col-lg-6">
            <x-ui.card title="Avatars and skeletons">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                        <x-ui.avatar :name="$patients[$loop->index % 4]['name']" :size="$size" />
                    @endforeach
                    <x-ui.avatar name="Nurse Joy" size="lg" ring />
                    <x-ui.avatar name="Square" size="lg" square />
                </div>
                <div class="vstack gap-3">
                    <x-ui.skeleton :lines="3" />
                    <x-ui.skeleton :rows="2" />
                    <div class="d-flex gap-2 align-items-center"><x-ui.skeleton circle size="40px" /><x-ui.skeleton width="40%" height="20px" /></div>
                </div>
            </x-ui.card>
        </div>
        <div class="col-12 col-lg-6">
            <x-ui.card title="Currently in clinic" quiet class="mb-3">
                <x-ui.empty-state quiet icon="door-open" title="Nobody is in the clinic right now." />
            </x-ui.card>
            <x-ui.card title="Empty states">
                <x-ui.empty-state module="patients" title="No patients yet" description="Add a patient to start logging clinic visits.">
                    <x-ui.button icon="person-plus" size="sm">Add patient</x-ui.button>
                </x-ui.empty-state>
                <x-ui.empty-state quiet icon="door-open" title="Nobody is in the clinic right now." />
            </x-ui.card>
        </div>
        <div class="col-12 col-lg-6">
            <x-ui.card title="Today's appointments" subtitle="Widget header with a module chip" module="appointments">
                <x-slot:actions><x-ui.button size="sm" variant="secondary">View all</x-ui.button></x-slot:actions>
                <div class="vstack gap-1">
                    <a href="#" class="list-row"><x-ui.avatar name="Maria Santos" size="sm" /><span class="flex-grow-1">Maria Santos</span><x-ui.status-badge status="pending" size="sm" /></a>
                    <a href="#" class="list-row"><x-ui.avatar name="Jose Reyes" size="sm" /><span class="flex-grow-1">Jose Reyes</span><x-ui.status-badge status="approved" size="sm" /></a>
                </div>
                <x-slot:footer>
                    <x-ui.button variant="ghost" size="sm">Dismiss</x-ui.button>
                    <x-ui.button size="sm">Continue</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </div>
    </div>
</div>

{{-- Modals --}}
@foreach (['sm', 'md', 'lg', 'xl'] as $size)
    <x-ui.modal id="demoModal{{ ucfirst($size) }}" :size="$size" title="Stock in" subtitle="Record medicine received from the supplier.">
        <p class="mb-0">This is a {{ $size }} modal. Esc or the close button dismisses it, and focus stays inside while it is open.</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button data-bs-dismiss="modal">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endforeach
<x-ui.modal id="demoModalForm" title="Cancel appointment" :action="route('ui.kit')" method="GET" sheet>
    <x-ui.textarea name="cancelled_reason" label="Reason" rows="3" required help="The guardian sees this in the SMS." />
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Keep appointment</x-ui.button>
        <x-ui.button type="submit" variant="danger">Cancel appointment</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

<x-ui.confirm-dialog />
<x-ui.flash-toasts :errors="false" />
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-demo-toast]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.toast(btn.dataset.message, btn.dataset.demoToast);
        });
    });
    var js = document.getElementById('demoConfirmJs');
    if (js) {
        js.addEventListener('click', async function () {
            var ok = await window.confirmDialog({ title: 'Archive 12 old logs?', message: 'Archived logs stay searchable in reports.', confirmText: 'Archive' });
            window.toast(ok ? 'Logs archived.' : 'Nothing changed.', ok ? 'success' : 'info');
        });
    }
});
</script>
@endpush
