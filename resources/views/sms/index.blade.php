@extends('layouts.app')

@section('title', 'SMS log')

@php
    $statusOptions = ['sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Waiting', 'skipped' => 'Skipped'];
    $filtered = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header title="SMS log"
        :description="'Every text message the system sent or tried to send. '.number_format($logs->total()).' '.\Illuminate\Support\Str::plural('message', $logs->total()).($filtered ? ' match the filters.' : '.')"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'SMS log' => null]">
        @can('send-sms')
            <x-slot:actions>
                <x-ui.button :href="route('sms.create')" icon="send">Send a text</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    @unless (settings('sms_enabled'))
        <x-ui.alert variant="warning" title="SMS is off">
            No text messages are being sent. New messages are listed here as Skipped.
            @can('manage-settings')
                <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="route('admin.settings.edit', 'sms')">Open SMS settings</x-ui.button></x-slot:actions>
            @endcan
        </x-ui.alert>
    @endunless

    @isset($stats)
        <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-xl-4">
            <div class="col"><x-ui.stat-card class="h-100" label="Sent" :value="number_format($stats['sent'])" icon="check2-circle" tone="success" sub="This month" /></div>
            <div class="col"><x-ui.stat-card class="h-100" label="Failed" :value="number_format($stats['failed'])" icon="x-circle" :tone="$stats['failed'] ? 'danger' : 'neutral'" sub="This month" /></div>
            <div class="col"><x-ui.stat-card class="h-100" label="Skipped" :value="number_format($stats['skipped'])" icon="slash-circle" tone="neutral" sub="This month, usually because SMS was off" /></div>
            <div class="col"><x-ui.stat-card class="h-100" label="Waiting" :value="number_format($stats['pending'])" icon="hourglass-split" :tone="$stats['pending'] ? 'warning' : 'neutral'" sub="Not sent yet" /></div>
        </div>
    @endisset

    <x-ui.filters :action="route('sms.index')" search-placeholder="Number, name or message"
        :labels="['status' => 'Status', 'date_from' => 'From', 'date_to' => 'To']"
        :options="['status' => $statusOptions]">
        <x-slot:inline>
            <x-ui.select name="status" size="sm" aria-label="Status" :options="$statusOptions" placeholder="Any status" :selected="$filters['status']" />
        </x-slot:inline>
        <x-ui.input name="date_from" type="date" size="sm" label="From" :value="$filters['dateFrom']" />
        <x-ui.input name="date_to" type="date" size="sm" label="To" :value="$filters['dateTo']" />
    </x-ui.filters>

    <x-ui.card flush>
        <x-ui.table :paginator="$logs" noun="messages" caption="SMS log" responsive="stack">
            <x-slot:head>
                <x-ui.th>Recipient</x-ui.th>
                <x-ui.th priority="md">Message</x-ui.th>
                <x-ui.th>Status</x-ui.th>
                <x-ui.th priority="lg">Reason</x-ui.th>
                <x-ui.th priority="xl">Type</x-ui.th>
                <x-ui.th priority="xl">Sent by</x-ui.th>
                <x-ui.th priority="sm">When</x-ui.th>
            </x-slot:head>

            @foreach ($logs as $log)
                <tr>
                    <x-ui.td identity>
                        <span class="cell-title d-block">{{ $log->recipient_name ?: $log->recipient_number }}</span>
                        @if ($log->recipient_name)<span class="cell-sub d-block tabular">{{ $log->recipient_number }}</span>@endif
                    </x-ui.td>
                    <x-ui.td priority="md" label="Message" truncate>{{ $log->message }}</x-ui.td>
                    <x-ui.td label="Status"><x-ui.status-badge :status="$log->status" type="sms" :label="$statusOptions[$log->status] ?? null" /></x-ui.td>
                    <x-ui.td priority="lg" label="Reason" :muted="$log->status === 'skipped'" :class="$log->status === 'failed' ? 'text-danger' : null" :truncate="(bool) $log->error_message">{{ $log->error_message ?: '-' }}</x-ui.td>
                    <x-ui.td priority="xl" label="Type" muted>{{ $log->event_label }}</x-ui.td>
                    <x-ui.td priority="xl" label="Sent by" muted>{{ $log->createdBy->name ?? 'System' }}</x-ui.td>
                    <x-ui.td priority="sm" label="When" muted>
                        <time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->format('M j, Y, g:i A') }}">{{ $log->created_at->format('M j, Y, g:i A') }}</time>
                    </x-ui.td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($filtered)
                    <x-ui.empty-state icon="search" title="No messages match these filters" description="Try a different search or clear the filters." compact>
                        <x-ui.button variant="secondary" size="sm" :href="route('sms.index')">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="chat-dots" title="No text messages yet" description="Messages appear here once the system sends or tries to send one." compact />
                @endif
            </x-slot:empty>
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
