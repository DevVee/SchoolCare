# UI kit (`x-ui.*` components)

Blade anonymous components on Bootstrap 5.3 + SCSS + Bootstrap Icons + vanilla JS.
No Tailwind, no Alpine. Living style guide: **`/ui-kit`** (route `ui.kit`, needs `manage-settings`).
Binding design rules: the owner's UI principles (Swiss minimal, one accent, no gradients, no em dashes in copy,
4/6/8/10 px radii, 44 px touch targets, Apple-style motion). Every example below follows them.

Contents: [Wiring a layout](#1-wiring-a-layout) · [Tokens](#2-tokens) · [JS APIs](#3-javascript-apis) ·
[Components](#4-components) · [Page recipes](#5-page-recipes) · [Legacy compatibility](#6-legacy-compatibility) · [Rules](#7-rules-do-and-do-not)

---

## 1. Wiring a layout

Every layout that renders app pages needs these (the shell phase adds them to `layouts/app.blade.php`,
`layouts/guest.blade.php` and the error pages):

```blade
<head>
    ...
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <x-ui.brand-style />          {{-- AFTER @vite: Figtree + Noto Sans fonts and the runtime brand colour --}}
    @stack('styles')
</head>
<body>
    ...
    <x-ui.confirm-dialog />       {{-- the ONE global confirm modal --}}
    <x-ui.flash-toasts />         {{-- session flashes as toasts; container for window.toast() --}}
    @stack('scripts')
</body>
```

* When adding `<x-ui.flash-toasts />`, delete the layout's inline `session('success'|'error'|'warning')` alerts,
  otherwise messages show twice.
* `x-ui.brand-style` props: `fonts` (bool, default true: outputs the Google Fonts link), `color` (override hex),
  `default` (`#2563EB`). It only prints a `<style>` block when `settings('brand_primary_color')` is a valid
  `#RRGGBB` different from the default.
* `resources/js/app.js` sets `window.bootstrap` (inline scripts may call `bootstrap.Modal` etc.). It is an ES
  module, so it runs after the HTML is parsed: inline page scripts must wrap Bootstrap calls in
  `DOMContentLoaded` (module scripts run before that event).
* Product naming: never hardcode a product or school name. Use `settings('app_name')`, `app_short_name`,
  `app_tagline`, `org_name` (hide when empty), `org_short_name`, `settings()->imageUrl('brand_logo', '/schoolcare-icon.svg')`,
  `settings()->imageUrl('school_logo', '')`, `header_title`, `topbar_show_school`, `ai_assistant_name`, or simply `<x-ui.logo>`.

## 2. Tokens

SCSS: `resources/scss/_tokens.scss` (Bootstrap overrides + `$tones`), `_root.scss` (CSS custom properties),
`_brand-runtime.scss` (re-points compiled Bootstrap primaries at `--brand-*`). Partials live in
`resources/scss/components/*` and `resources/scss/layout/*`; `pages/_legacy-shell.scss` styles the CURRENT layout
and is deleted with the shell rewrite.

| Token | Value | Use |
|---|---|---|
| `--brand-50 ... --brand-950`, `--brand-rgb`, `--brand-contrast` | royal blue, 600 = `#2563EB` | runtime themable; `rgba(var(--brand-rgb), .2)` |
| `--c-{tone}-{50,100,200,600,700,text,rgb}` | tones: `brand success warning danger info neutral orange teal cobi` | tints, icons, text on tint |
| `--c-ink` / `--c-ink-2` / `--c-muted` | `#0F172A` / `#475569` / `#64748B` | text (never lighter than muted) |
| `--c-border` / `--c-border-strong` | `#E2E8F0` / `#CBD5E1` | hairlines |
| `--c-canvas` / `--c-surface` / `--c-surface-2` | `#F8FAFC` / `#FFF` / `#F1F5F9` | backgrounds |
| Radii (`$radius-xs/sm/md/lg`) | 4 / 6 / 8 / 10 px | tags, checkboxes / buttons, inputs, nav, menu items / cards, menus, toasts, alerts / modals |
| Fonts | Figtree (`$font-ui`, `$font-display`), Noto Sans (`$font-body`) | UI labels, headings, numbers / body |
| Motion | `--c-dur-press` 100ms, `--c-dur-fast` 150ms, `--c-dur-base` 200ms, `--c-dur-slow` 250ms, `--c-press-scale` .97 | |
| Layout | `--c-sidebar-w` 260px, `--c-sidebar-cw` 72px, `--c-topbar-h` 58px, `--c-content-max` 1400px, `--c-sticky-top` | |

**One colour theme**: every icon, chip, stat and status is the brand blue (runtime themable); red (`danger`) is
kept for destructive actions and danger states, slate (`neutral`) for quiet ones. Icons are plain glyphs, never
on a tinted square. Module keys (`ui.module_meta` = label + default icon, `ui.module_aliases` e.g. `patient-logs` to `logbook`):
`overview` · `logbook` · `patients` · `appointments` · `consultations` · `medicines` · `inventory` · `dispensing` ·
`reports` · `sms` · `ai` · `admin`. CSS: `--m-{key}`, `--m-{key}-rgb`, `.tone-{key}` (glyph colour),
`.tone-chip-{key}` (glyph colour, no background), also `.tone-{tone}` / `.tone-chip-{tone}` for the plain tones.
Still banned: gradients, glows, coloured shadows, big saturated blocks.

Tone names accepted by components: the tones above plus Bootstrap names (`primary`=brand, `secondary`/`light`/`dark`=neutral,
`success`, `warning`, `danger`, `info`) and `bg-*`/`text-bg-*` prefixes. Mapping lives in `config/ui.php`.
Helper classes: `.text-ink`, `.text-ink-2`, `.text-brand`, `.bg-brand`, `.bg-brand-subtle`, `.bg-canvas`, `.bg-surface-2`,
`.tone-bg-{tone}` (tint + icon colour), `.overline`, `.tabular`, `.font-ui`, `.font-display`, `.min-w-0`, `.pressable`.
Font size utilities added: `.fs-sm` (14), `.fs-xs` (13), `.fs-2xs` (12).

## 3. JavaScript APIs

| API | File | Notes |
|---|---|---|
| `window.bootstrap` | `app.js` | full Bootstrap ESM namespace |
| `window.toast(message, type = 'success', {title, delay, autohide})` or `toast({type, title, message})` | `ui/toast.js` | types `success error warning info`; errors stay until closed |
| `window.confirmDialog({title, message, variant, confirmText, cancelText, icon, input, inputLabel, inputRequired})` | `ui/confirm.js` | resolves `false`, `true`, or the typed text when `input` is set |
| `[data-confirm]` on a `<form>`, submit button, link or button | `ui/confirm.js` | see below; replaces native `confirm()` |
| `form[data-autosubmit]` | `ui/filters.js` | select/date/checkbox change submits; `data-autosubmit="debounce"` also submits 250ms after typing |
| `form[data-filter-form]` | `ui/filters.js` | empty params are dropped from the URL |
| `window.charts.init(root)`, `.render(el, config)`, `.destroy(el)` | `ui/charts.js` | ApexCharts, lazy chunk |
| `.app-topbar.is-scrolled` | `ui/scroll-edge.js` | toggled on scroll |
| `[data-live-clock]` (+ `data-timezone`) | `ui/clock.js` | x-ui.hero date/time, every second, paused when hidden |
| Double-submit guard | `app.js` (clinical agent) | disables submit buttons on non-GET submit; runs after the confirm dialog |

`data-confirm` attributes:

| Attribute | Meaning |
|---|---|
| `data-confirm="Body text"` | required; the message (may be empty) |
| `data-confirm-title` | heading, default "Are you sure?" |
| `data-confirm-variant` | `danger` `primary` `warning` `success` `info`; default `danger` for `@method('DELETE')` forms, else `primary` |
| `data-confirm-button` / `data-confirm-cancel` | button labels (default "Delete" for danger, "Confirm" otherwise / "Cancel") |
| `data-confirm-icon` | Bootstrap icon name |
| `data-confirm-input="field"` + `data-confirm-input-label` (+ `data-confirm-input-required="false"`) | shows a textarea; its value is submitted as hidden input `field` |
| `data-confirm-action` + `data-confirm-method` | on a plain button: builds and submits a CSRF form to that URL |

```blade
<form method="POST" action="{{ route('patients.destroy', $p) }}" data-confirm="Visit history is kept."
      data-confirm-title="Delete {{ $p->full_name }}?" data-confirm-button="Delete patient">
    @csrf @method('DELETE')
    <x-ui.button type="submit" variant="danger" icon="trash">Delete</x-ui.button>
</form>

<button type="button" class="btn btn-secondary" data-confirm="The guardian will get a text."
        data-confirm-title="Cancel appointment?" data-confirm-variant="warning"
        data-confirm-input="cancelled_reason" data-confirm-input-label="Reason"
        data-confirm-action="{{ route('appointments.cancel', $a) }}" data-confirm-method="PATCH">Cancel appointment</button>
```

## 4. Components

Every component merges extra attributes (`class`, `id`, `data-*`, `aria-*`) onto its root element unless noted.
Boolean props can be written bare (`required`, `flush`) or bound (`:required="$x"`).

### Layout and content

**`x-ui.page-header`** `title` (req), `description` (alias `subtitle`), `breadcrumbs` (`['Label' => url|null]` or
`[['label'=>..,'url'=>..]]`, last = current page), `back` (url), `back-label` (default "Back"). Slots: `actions`,
default (meta row under the description). No icon beside or above the title: `module` / `icon` are accepted for
backwards compatibility but ignored.
```blade
<x-ui.page-header title="Patients" description="134 patients, 120 active"
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => null]">
    <x-slot:actions><x-ui.button :href="route('patients.create')" icon="person-plus">New patient</x-ui.button></x-slot:actions>
</x-ui.page-header>
```

**`x-ui.card`** `title`, `subtitle` (alias `description`), `module` (widget header chip in the module tone), `icon`
(chip icon; with `icon-tone` when there is no module), `icon-tone`, `padding` (`none|sm|md|lg`, default md),
`flush` (= padding none, for tables), `variant` (`default|flat|interactive`), `href` (whole card is a link),
`body-class`, `quiet` (empty panel: flat, tinted, compact; pair with `x-ui.empty-state quiet`).
Slots: `actions` (header right), `header` (replaces the header), `footer`.

**`x-ui.section`** (form section) Default is STACKED: title + one-line description on top, fields below at full width.
Props: `title` (req), `description`, `columns` (`2|3`: direct children on a responsive grid, 1 column on phones, 2 from sm,
3 from lg; give textareas and lists `wrapper-class="col-full"` / `class="col-full"`), `aside` (opt-in old two-column layout,
title left and fields right from lg; avoid, it wastes width). Without `columns`, bring your own `row g-3` grid.
The `.form-grid` / `.form-grid-3` / `.col-full` classes also work outside sections.

**`x-ui.section-nav`** (settings nav: sticky list on desktop, horizontal tabs on mobile) `items`
(`key => label` or `['label','href','icon','count']`), `active`, `title`, `param` (build `?param=key` links; otherwise `#key`).
Sticky offset: `--c-sticky-top` (defaults to topbar height + 16px; the legacy shell sets it for its fixed header).
```blade
<div class="row g-4">
  <div class="col-lg-3"><x-ui.section-nav title="Settings" :active="$group" :items="$groups" /></div>
  <div class="col-lg-9">...</div>
</div>
```

**`x-ui.description-list`** `items` (`label => value`, empty values show `empty`), `empty` (default "Not recorded"),
`layout` (`grid|stacked|compact`). Children: **`x-ui.description-item`** `label`, `empty`; slot = value (HTML allowed).

**`x-ui.stat-card`** (every page that shows summary numbers) White card, uppercase label top-left, 44px tinted icon box
top-right, big number in the card's tone, bottom meta line. Props: `label` (req), `value`, `icon` (default: the module
icon), `tone` (module key or alias, or a colour: `brand rose amber teal cyan indigo sky green emerald violet purple slate`,
also `success warning danger info neutral orange`; default brand), `href`, back-compat `delta` / `trend` / `sub`.
Default slot or `meta` slot = bottom line. Markers: `<span class="stat-mark mark-up">+3</span>` (`mark-up` green,
`mark-down` red, `mark-warn` brand), `<span class="stat-meta-item"><span class="stat-dot tone-amber"></span>Leave 1</span>`,
icons with `class="tone-sky"`.
```blade
<x-ui.stat-cards cols="5">
    <x-ui.stat-card label="Total patients" :value="$total" tone="patients" :href="route('patients.index')">
        <span class="stat-mark mark-up">+{{ $new }}</span> added this month
    </x-ui.stat-card>
    <x-ui.stat-card label="Staff on duty" :value="$onDuty" icon="person-badge" tone="teal">
        <span class="stat-meta-item"><span class="stat-dot tone-green"></span>Active {{ $active }}</span>
        <span class="stat-meta-item"><span class="stat-dot tone-amber"></span>Leave {{ $leave }}</span>
    </x-ui.stat-card>
</x-ui.stat-cards>
```

**`x-ui.stat-cards`** responsive equal-height row: 1 column on small phones, 2 on phones and tablets, `cols` per row
on xl (default 4; use 5 for five cards), at most 4 at lg.

**`x-ui.stat-strip`** array shortcut that renders the same stat cards: `items` = `[['label','value','href'?,'hint'?,'module'?,'tone'?,'icon'?]]`
(`hint` becomes the bottom line; `tone` wins over `module` for colour), `cols` (default = number of items, max 5).

**`x-ui.hero`** (dashboard only) Royal-blue banner with a subtle same-hue diagonal gradient from the runtime brand colour
and one soft circle at the right (the ONLY gradient allowed). Props: `title` (default "Good morning|afternoon|evening,
{first name}" in the app timezone), `subtitle`, `clock` (live "Saturday, September 26, 2026 · 1:12:00 PM" line, default
true; updates every second, pauses while the tab is hidden), `timezone`, `name`. Slot `actions`: use
`x-ui.button variant="hero"` (white, brand text) and `variant="hero-outline"` (white 40% border). Stacks on phones.
```blade
<x-ui.hero subtitle="Clinic visits, patients and stock at a glance.">
    <x-slot:actions>
        <x-ui.button variant="hero" icon="journal-plus" :href="route('patient-logs.create')">Log a visit</x-ui.button>
        <x-ui.button variant="hero-outline" icon="calendar-check" :href="route('appointments.index')">Appointments</x-ui.button>
    </x-slot:actions>
</x-ui.hero>
```

**`x-ui.empty-state`** `title` (req), `description`, `icon` (default inbox; with `module` the module icon), `module`
(icon in the module tone), `tone` (default brand), `compact`, `quiet`
(single muted line for empty panels). Default slot or `actions` slot = buttons (use `size="sm"`).

**`x-ui.logo`** `variant` (`plain|sidebar|topbar|auth`), `size` (px), `wordmark` (bool), `href`, `inverse` (white text).
Sidebar title follows `settings('header_title')` (`app|school|both`); topbar shows the school name + school logo when
`topbar_show_school` is on and `org_name` is set, else the app short name.

**`x-ui.icon-chip`** `module` (key or alias) or `tone`, `icon` (default: module icon), `size` (`sm 28|md 32|lg 36`),
`label` (accessible name; omit when decorative). Plain glyph in the tone colour, no background.

**`x-ui.avatar`** `name` (initials on the brand tint circle), `src`, `size` (`xs 24|sm 32|md 40|lg 48|xl 64`), `ring`, `square`, `alt`.
**`x-ui.skeleton`** `width`, `height`, `lines`, `rows`, `circle` + `size`, `label`. Async content only.
**`x-ui.icon`** `name` (with or without `bi-`), `size` (px or CSS length), `label` (accessible name; omit = decorative).

### Actions

**`x-ui.button`** `variant` (`primary|secondary|outline|ghost|danger|success|warning|link`, plus `hero|hero-outline` inside `x-ui.hero`), `size` (`xs|sm|md|lg`),
`href` (renders `<a>`), `type` (default `button`), `icon`, `icon-right`, `loading` (spinner + disabled), `disabled`,
`block`, `icon-only` + `label` (aria-label and tooltip).

**`x-ui.action-menu`** THE table row actions pattern: one ghost "more" button, menu of text items.
Props: `for` (row name, gives "Actions for {name}"), `label` (full aria-label), `align` (default end), `icon` (default three-dots).
Items: **`x-ui.action-menu.item`** `href` | `action` + `method` (`POST|PATCH|PUT|DELETE|GET`), `icon`, `danger`, `confirm`,
`confirm-title`, `confirm-button`, `disabled`, `target`, `title` (tooltip, e.g. why an item is disabled).
**`x-ui.action-menu.divider`**.
Order: View, Edit, situational items, divider, destructive last.
```blade
<x-ui.td actions>
    <x-ui.action-menu :for="$user->name">
        <x-ui.action-menu.item :href="route('admin.users.show', $user)" icon="eye">View</x-ui.action-menu.item>
        <x-ui.action-menu.item :href="route('admin.users.edit', $user)" icon="pencil">Edit</x-ui.action-menu.item>
        <x-ui.action-menu.item :action="route('admin.users.toggle-active', $user)" method="PATCH" icon="person-dash"
            confirm="They cannot sign in until reactivated." :confirm-title="'Deactivate '.$user->name.'?'" confirm-button="Deactivate">Deactivate</x-ui.action-menu.item>
        <x-ui.action-menu.divider />
        <x-ui.action-menu.item :action="route('admin.users.destroy', $user)" method="DELETE" icon="trash" danger
            confirm="This permanently removes the account." :confirm-title="'Delete '.$user->name.'?'" confirm-button="Delete user">Delete</x-ui.action-menu.item>
    </x-ui.action-menu>
</x-ui.td>
```

**`x-ui.dropdown`** `label`, `icon`, `variant` (trigger button, default secondary), `size`, `align` (`start|end`),
`width` (`menu|panel|panel-wide`), `caret`, `offset`, `fixed` (Popper fixed strategy). Slots: `trigger` (custom trigger
with `data-bs-toggle="dropdown"`), default (items).
**`x-ui.dropdown-item`** `href` | `action` + `method`, `icon`, `tone="danger"`, `label` (or slot), `meta`, `active`,
`disabled`, `confirm`, `confirm-title`, `confirm-button`, `confirm-variant`, `target`.
**`x-ui.dropdown-divider`**, **`x-ui.dropdown-header`** (slot).

### Tables

**`x-ui.table`** `responsive` (`scroll` default | `stack`: rows become cards below `stack-at`), `stack-at` (`sm|md`),
`dense`, `striped`, `hover` (default true), `sticky` (pin first column), `min-width`, `max-height`, `caption`
(visually hidden), `columns` (colspan of the empty row), `paginator` + `noun` (footer "Showing 1 to 20 of 134 patients"),
`table-class`. Slots: `head` (the `x-ui.th` cells), default (rows), `empty` (shown when there are no rows), `toolbar`,
`footer` (replaces the paginator footer), `foot` (`<tfoot>` rows).

**`x-ui.th`** `sortable` (column key: links to `?sort=key&dir=asc|desc`, keeps other params, resets page),
`priority` (`sm|md|lg|xl|xxl`: hidden below that breakpoint), `align` (`start|center|end`), `width`,
`sort-param` / `dir-param` (default `sort` / `dir`).

**`x-ui.td`** `label` (shown before the value in stack mode), `priority` (same as its th), `align`, `identity`
(primary cell: card title in stack mode), `actions` (right aligned; top-right in stack mode), `numeric`, `wrap`,
`muted`, `truncate` (ellipsis at 240px with a title tooltip).

Rows stay on one line: cells are `nowrap`; use `truncate` for long text and `wrap` only for description columns.
Identity cell markup (name link + one muted line):
```blade
<x-ui.td identity>
    <div class="identity">
        <x-ui.avatar :name="$p->full_name" size="sm" />
        <div class="identity-text">
            <a href="{{ route('patients.show', $p) }}" class="identity-title">{{ $p->full_name }}</a>
            <span class="identity-sub">{{ $p->patient_number }}</span>
        </div>
    </div>
</x-ui.td>
```

**`x-ui.pagination`** `paginator` (req), `noun`, `on-each-side` (default 1). Keeps the query string.
Plain `{{ $x->links() }}` renders the same markup (`Paginator::defaultView('pagination::schoolcare')`,
`defaultSimpleView('pagination::schoolcare-simple')`, views in `resources/views/vendor/pagination/`).

### Forms

All controls: `old()` on validation failure, `is-invalid` + `aria-invalid` + `aria-describedby` from `$errors`
(`bag` prop, default `default`), array names supported (`items[0][qty]` reads error key `items.0.qty`).
Id defaults to `f-{name}` (non-alphanumerics become `-`). Giving `label` or `help` wraps the control in `x-ui.field`;
`wrapper-class` goes on that wrapper (use grid classes: `col-12 col-sm-6`).

**`x-ui.input`** `name` (req), `type`, `value`, `label`, `help`, `required`, `optional`, `disabled`, `readonly`,
`size` (`sm|lg`), `icon` (leading), `id`, `invalid`, `bag`, `wrapper-class`. Dates/Carbon values are formatted for the input type.
**`x-ui.select`** `name`, `options` (`value => label` or `group => [value => label]`), `selected` (alias `value`;
scalar, array or Collection), `placeholder` (empty first option), `multiple`, plus the input props. Slot = extra `<option>`s.
**`x-ui.textarea`** `name`, `value` (or slot), `rows` (default 4), plus the input props.
**`x-ui.checkbox`** `name`, `value` (default 1), `checked`, `label` (or slot), `description`, `type` (`checkbox|radio`),
`unchecked-value` (hidden input), `inline`, `disabled`, `required`, `id`, `bag`.
**`x-ui.switch`** `name`, `checked`, `label`, `description`, `value` (1), `unchecked-value` (default "0", always submitted), `disabled`, `size` (`lg|md`).
**`x-ui.label`** `for`, `required` (red `*` + "(required)" for screen readers), `optional` ("Optional" tag), `value` (or slot).
**`x-ui.field`** `label`, `name` (error lookup), `for`, `required`, `optional`, `help`, `bag`; slot = any control.
**`x-ui.field-error`** `name`, `bag`: renders the message only when invalid.
**`x-ui.search-input`** `name` (default `search`), `value` (default request), `placeholder`, `label` (sr), `size`,
`clearable` (x link that drops the param), `clear-url`, `id`.

```blade
<form method="POST" action="{{ route('patients.store') }}">
    @csrf
    <x-ui.card>
        <x-ui.section title="Identity" description="Name as it appears on school records.">
            <div class="row g-3">
                <x-ui.input wrapper-class="col-12 col-sm-6" name="first_name" label="First name" required />
                <x-ui.input wrapper-class="col-12 col-sm-6" name="last_name" label="Last name" required />
                <x-ui.select wrapper-class="col-12 col-sm-6" name="category" label="Category" :options="$categories" placeholder="Choose" required />
                <x-ui.input wrapper-class="col-12 col-sm-6" name="birthdate" type="date" label="Birthdate" optional />
            </div>
        </x-ui.section>
        <x-slot:footer>
            <x-ui.button variant="secondary" :href="route('patients.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check-lg">Save patient</x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</form>
```

### Filters, tabs

**`x-ui.filters`** GET toolbar card. Props: `action` (default current URL), `search` (bool), `search-name` (default `search`),
`search-placeholder`, `labels` (param => chip label for EVERY filter field, inline or panel), `options`
(param => [value => text] for chip text), `keep` (params carried as hidden inputs; default `sort dir per_page tab`),
`reset-url`, `autosubmit` (default true), `panel-title`, `id`.
Slots: default (fields in the "Filters" dropdown panel, with Apply and Clear all), `inline` (1 or 2 primary filters:
`x-ui.date-range`, or bare controls with `aria-label`), `pills`, `stats` and `actions` (right side, after Filters).
Active filters show as removable chips plus "Clear all".
Layout: one row on desktop (search grows, inline controls about 160px each, Filters and actions right-aligned);
on phones search is full width, then the inline filters side by side, then Filters.
```blade
<x-ui.filters :action="route('patient-logs.index')" search-placeholder="Patient name or complaint"
    :labels="['date_from' => 'From', 'date_to' => 'To', 'disposition' => 'Outcome', 'grade' => 'Grade']"
    :options="['disposition' => $dispositionLabels]">
    <x-slot:inline>
        <x-ui.date-range from-name="date_from" to-name="date_to" :from="$f['dateFrom']" :to="$f['dateTo']" :max="$today" />
    </x-slot:inline>
    <x-ui.select name="disposition" label="Outcome" size="sm" :options="$dispositionLabels" placeholder="Any outcome" :selected="request('disposition')" />
    <x-ui.select name="grade" label="Grade" size="sm" :options="$grades" placeholder="All grades" :selected="request('grade')" />
</x-ui.filters>
```

**`x-ui.date-range`** "From [date] to [date]" on one line (inputs side by side on phones). Props: `from-name` (`from`),
`to-name` (`to`), `from`, `to` (values; default old input, then the request), `min`, `max` (both inputs; the "to" input's
min follows the "from" value), `label` (group aria-label, default "Date range"), `size` (`sm|md`), `bag`.
Shows validation errors for either name. Register both names in `x-ui.filters` `labels`.
Do not hand-build ranges with two `x-ui.input` + a "to" span.

**`x-ui.tabs`** (link based, deep-linkable) `items` (`key => label` or `['label','href','icon','count']`), `active`
(default `request($param)` or first), `param` (default `tab`; used when an item has no href), `variant`
(`segmented|underline`), `block`, `label` (aria). For same-page panes use Bootstrap tab markup with
`.nav.nav-segmented` / `.nav.nav-underline` and `data-bs-toggle="tab"`.

**`x-ui.count`** `value`, `max` (99, shows "99+"), `urgent` (red, only urgent AND actionable), `brand`, `show-zero`, `label`.
Neutral by default; renders nothing for 0/null.

### Status

**`x-ui.badge`** `color` (alias `tone`; tone, colour name `rose amber teal cyan indigo sky green emerald violet purple slate`,
module key or alias, or Bootstrap name; `bg-*` accepted), `variant` (`soft|solid|outline`),
`dot` (default true), `icon`, `size` (`sm|md|lg`). Works with model accessors: `:color="$appointment->status_badge"`.
**`x-ui.status-badge`** `status` (string, bool, 1/0), `type` (`appointment disposition audit inventory sms patient stock role`),
`label`, `variant`, `size`, `dot`. Type `severity`: mild/low amber, moderate orange, severe/high rose, critical danger.
Tone and label come from `config('ui.status')` / `config('ui.status_labels')`; unknown
statuses are neutral with a headline label. Role badges: use `:dot="false"`; Administrator is brand, never red.

### Feedback

**`x-ui.alert`** `variant` (alias `tone`: `success|danger|error|warning|info|brand|neutral`), `title`, `icon`
(auto; `:icon="false"` hides), `dismissible`, `accent` (4px left border). Slots: default, `actions`.
**`x-ui.flash-toasts`** `errors` (bool, default true: adds a validation summary toast). One per page (layout).
**`x-ui.confirm-dialog`** no props. One per page (layout).
**`x-ui.modal`** `id` (req), `title`, `subtitle`, `size` (`sm 440|md 520|lg 640|xl 800|fullscreen`), `static`,
`scrollable` (default true), `centered` (default true), `sheet` (bottom sheet on phones), `action` + `method` + `files`
(wraps body and footer in a CSRF form), `close-label`. Slots: default (body), `footer`, `header`.
Open with `data-bs-toggle="modal" data-bs-target="#id"`.

### Charts

**`x-ui.chart`** (ApexCharts, bundled; the chunk loads only on pages with a chart; no CDN)
`type` (`line|area|bar|horizontal-bar|stacked-bar|donut`), `series` (`[['name'=>..,'data'=>[..]]]`, a flat list = one series;
donut: flat numbers + `labels`, or `label => value`), `categories`, `labels`, `title`, `subtitle`, `height` (280),
`table` (default true: "Show table" toggle; false keeps a visually hidden table for screen readers), `empty`
(message; an empty state replaces the chart when every value is 0 or missing), `y-format`
(`integer|decimal|percent|currency`), `currency` (PHP), `total-label` (donut centre), `id`.
Colours: fixed validated order `#2563EB` (runtime brand), `#EB6834`, `#1BAF7A`, `#EDA100`, `#E87BA4`, `#008300`; a 7th+
series folds into "Other"; single series is always the brand colour. Legend shows for 2+ series and donuts.
```blade
<x-ui.card>
    <x-ui.chart type="bar" title="Visits per day" subtitle="This week"
        :series="[['name' => 'Visits', 'data' => $visitCounts]]" :categories="$dayLabels" />
</x-ui.card>
```

## 5. Page recipes

List page: `x-ui.page-header` (title, count in description, primary action) → optional `x-ui.stat-strip` →
`x-ui.filters` → `x-ui.card flush` containing `x-ui.table :paginator` with `x-ui.action-menu` rows and an `empty` slot
(first-use empty state with a create button; filtered empty state with "Clear filters").

Form page: `x-ui.page-header :back` → `<form>` → `x-ui.card` with stacked `x-ui.section`s (`columns="2|3"`) and a footer (Cancel secondary, Save primary).
Put an `x-ui.alert variant="danger" title="Please fix the errors below"` above the card when `$errors->any()`.

Dashboard: `x-ui.hero` with 1 or 2 actions, then `x-ui.stat-cards`, then `x-ui.card :module` widgets and `x-ui.chart`s.
(`.welcome-band`, a flat brand-50 band, remains for lighter hub pages.)

Show page: `row g-4` → `col-lg-4` card with avatar, name, badges, `x-ui.description-list` → `col-lg-8` card with
`x-ui.tabs variant="underline"` and dense tables.

Settings: `row g-4` → `col-lg-3` `x-ui.section-nav` → `col-lg-9` cards; switches via `x-ui.switch`; a sticky `.save-bar`
(`<div class="save-bar">...buttons...</div>` as the last child of the form) for long forms.

## 6. Legacy compatibility

Un-migrated views pick up the new look automatically:
`text-primary`, `bg-primary`, `btn-outline-primary`, links, focus rings, pagination, `nav-tabs` = brand (runtime themable);
`bg-*-subtle text-*-emphasis` = tone palette; `text-muted` = `#64748B`; `card border-0 shadow-sm` = 8px card with a soft shadow;
`thead.table-light` = slate header; `btn-outline-secondary` = secondary button; `btn-xs` now exists;
`badge rounded-pill` and `btn rounded-pill` are squared to 4/6px; `$x->links()` = branded pagination;
old `--gradient-*` CSS variables resolve to SOLID colours; `.stat-card`, `.welcome-banner`, `.page-header`,
auth split-screen classes are kept and flattened (no gradients, glows or dot grids).
`[data-confirm]` now opens the branded modal (was native `confirm()`); inline `onsubmit="return confirm(...)"` still works.

## 7. Rules (do and do not)

* No em or en dashes in UI copy (use a comma, colon, parentheses or "to"). No gradients (the dashboard `x-ui.hero` is the one
  exception), glows, dot grids, pulsing dots or emoji.
* One accent: brand blue. Violet only as the small Cobi AI icon tone. Red means danger or error, never a plain count or a role.
* Row actions: `x-ui.action-menu` only. Never a row of coloured icon buttons.
* Summary numbers: `x-ui.stat-cards` / `x-ui.stat-card` (or the `x-ui.stat-strip` shortcut).
* Colour with purpose: module tones on icons and chips only (sidebar icons, stat strip, widget headers, empty states).
  Never an icon or chip beside or above a page title.
* Filters: search + 1 or 2 inline filters; the rest in the Filters panel.
* Empty states: compact; empty side panels use `quiet`.
* Plain language: no developer terms in the UI (API key names, queue, cache, JSON, regex).
* Every input has a visible label (inline filter controls use `aria-label`). Icon-only buttons need `label`.
* 44px touch targets on phones are built in; do not shrink them with custom CSS.
