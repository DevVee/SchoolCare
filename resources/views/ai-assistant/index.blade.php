@extends('layouts.app')

@php
    $assistant = settings('ai_assistant_name') ?: 'Assistant';
    $chronological = $conversations->sortBy('created_at')->values();
    $prompts = [
        'How do I add a new patient?',
        'How do I approve an appointment?',
        'How do I record dispensed medicine?',
        'What reports are available?',
        'How do text message alerts work?',
        'Explain a medical term',
    ];
@endphp

@section('title', $assistant)

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$assistant"
        description="Ask quick questions about the system, clinic work and health topics. Answers can be wrong, so check anything medical with a doctor or nurse."
        :breadcrumbs="['Dashboard' => route('dashboard'), $assistant => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="trash" id="clearBtn" :disabled="$conversations->isEmpty()">Clear chat</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="chat-layout">
        {{-- Recent questions (desktop). Clicking one scrolls to it in the chat. --}}
        <aside class="chat-history card" aria-labelledby="chatHistoryTitle">
            <div class="chat-history-head">
                <h2 class="chat-history-title" id="chatHistoryTitle">Recent questions</h2>
                <x-ui.count :value="$conversations->count()" />
            </div>
            <div class="chat-history-list" id="historyList">
                @forelse ($conversations as $convo)
                    <a href="#msg-{{ $convo->id }}" class="chat-history-item" title="{{ $convo->message }}">
                        <span class="chat-history-text">{{ \Illuminate\Support\Str::limit($convo->message, 60) }}</span>
                        <span class="chat-history-time">{{ $convo->created_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <x-ui.empty-state quiet icon="chat-square" title="No questions yet." />
                @endforelse
            </div>
        </aside>

        <section class="chat-panel card" aria-label="Chat with {{ $assistant }}">
            <div class="chat-messages" id="chatMessages" aria-live="polite" aria-relevant="additions">
                @if ($chronological->isEmpty())
                    <div id="emptyState" class="chat-empty">
                        <x-ui.empty-state icon="chat-square-text" :title="'Ask '.$assistant.' a question'"
                            description="For example how to do something in the system, or what a medical term means. Do not type private patient details." />
                    </div>
                @else
                    @foreach ($chronological as $convo)
                        <div class="msg-row is-user" id="msg-{{ $convo->id }}">
                            <div class="msg-body">
                                <div class="msg-bubble">{{ $convo->message }}</div>
                                <div class="msg-meta">You, {{ $convo->created_at->format('M j, g:i A') }}</div>
                            </div>
                        </div>
                        <div class="msg-row is-assistant">
                            <span class="msg-avatar tone-bg-cobi" aria-hidden="true"><x-ui.icon name="chat-square-text" /></span>
                            <div class="msg-body">
                                <div class="msg-bubble" data-markdown>{{ $convo->response }}</div>
                                <div class="msg-meta">{{ $assistant }}, {{ $convo->created_at->format('M j, g:i A') }}</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="chat-compose">
                <form id="chatForm" class="chat-input" autocomplete="off">
                    <label for="chatInput" class="visually-hidden">Your question</label>
                    <textarea id="chatInput" class="form-control" rows="1" maxlength="4000"
                              placeholder="Type your question"></textarea>
                    <x-ui.button type="submit" icon="send" icon-only :label="'Send to '.$assistant" id="sendBtn" disabled />
                </form>
                <div class="chat-compose-foot">
                    <span class="text-muted fs-xs">Enter to send, Shift and Enter for a new line.</span>
                    <span class="text-muted fs-xs tabular" id="charCount">0 / 4000</span>
                </div>
                <div class="chat-prompts" role="group" aria-label="Example questions">
                    @foreach ($prompts as $prompt)
                        <button type="button" class="btn btn-secondary btn-xs" data-prompt="{{ $prompt }}">{{ $prompt }}</button>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var assistantName = @json($assistant);
    var chatUrl = @json(route('ai-assistant.chat'));
    var clearUrl = @json(route('ai-assistant.clear'));
    var chat = document.getElementById('chatMessages');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var sendBtn = document.getElementById('sendBtn');
    var clearBtn = document.getElementById('clearBtn');
    var charCount = document.getElementById('charCount');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var busy = false;

    function escapeHtml(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Small, safe markdown: the text is escaped first, then a few patterns are formatted.
    function renderMarkdown(text) {
        return escapeHtml(text)
            .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
            .replace(/^#{1,6} (.+)$/gm, '<strong>$1</strong>')
            .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
            .replace(/^[-•] (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>\n?)+/g, function (m) { return '<ul>' + m.replace(/\n/g, '') + '</ul>'; })
            .replace(/\n{2,}/g, '<br><br>')
            .replace(/\n/g, '<br>');
    }

    document.querySelectorAll('.msg-bubble[data-markdown]').forEach(function (el) {
        el.innerHTML = renderMarkdown(el.textContent);
        el.removeAttribute('data-markdown');
    });

    function scrollBottom() { chat.scrollTop = chat.scrollHeight; }
    scrollBottom();

    function timeNow() {
        return new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function appendMessage(role, content, opts) {
        opts = opts || {};
        var empty = document.getElementById('emptyState');
        if (empty) empty.remove();
        var row = document.createElement('div');
        row.className = 'msg-row ' + (role === 'user' ? 'is-user' : 'is-assistant') + (opts.error ? ' is-error' : '');
        var body = role === 'user' ? escapeHtml(content).replace(/\n/g, '<br>') : renderMarkdown(content);
        var who = role === 'user' ? 'You' : assistantName;
        row.innerHTML = (role === 'user' ? '' : '<span class="msg-avatar tone-bg-cobi" aria-hidden="true"><i class="bi bi-chat-square-text"></i></span>')
            + '<div class="msg-body"><div class="msg-bubble">' + body + '</div>'
            + '<div class="msg-meta">' + escapeHtml(who) + ', ' + escapeHtml(timeNow()) + '</div></div>';
        chat.appendChild(row);
        scrollBottom();
        return row;
    }

    function showTyping() {
        var row = document.createElement('div');
        row.className = 'msg-row is-assistant';
        row.id = 'typingRow';
        row.innerHTML = '<span class="msg-avatar tone-bg-cobi" aria-hidden="true"><i class="bi bi-chat-square-text"></i></span>'
            + '<div class="msg-body"><div class="msg-bubble msg-typing"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> '
            + escapeHtml(assistantName) + ' is writing an answer</div></div>';
        chat.appendChild(row);
        scrollBottom();
    }
    function hideTyping() {
        var t = document.getElementById('typingRow');
        if (t) t.remove();
    }

    function syncInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        sendBtn.disabled = busy || !input.value.trim();
        charCount.textContent = input.value.length + ' / 4000';
    }
    input.addEventListener('input', syncInput);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            send(input.value.trim());
        }
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        send(input.value.trim());
    });

    async function send(text) {
        if (!text || busy) return;
        busy = true;
        input.value = '';
        syncInput();
        appendMessage('user', text);
        showTyping();
        try {
            var res = await fetch(chatUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            var data = {};
            try { data = await res.json(); } catch (err) { data = {}; }
            hideTyping();
            if (res.ok) {
                appendMessage('assistant', data.response || '');
                if (clearBtn) clearBtn.disabled = false;
            } else if (res.status === 419 || data.expired) {
                appendMessage('assistant', 'Your session has expired. The page will reload in a moment so you can continue.', { error: true });
                setTimeout(function () { window.location.reload(); }, 3000);
            } else if (res.status === 429) {
                appendMessage('assistant', 'You are sending questions too quickly. Please wait a moment and try again.', { error: true });
            } else {
                appendMessage('assistant', data.response || data.message || 'Something went wrong. Please try again.', { error: true });
            }
        } catch (err) {
            hideTyping();
            appendMessage('assistant', 'Could not reach the assistant. Check your internet connection and try again.', { error: true });
        } finally {
            busy = false;
            syncInput();
            input.focus();
        }
    }

    document.querySelectorAll('[data-prompt]').forEach(function (btn) {
        btn.addEventListener('click', function () { send(btn.dataset.prompt); });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', async function () {
            var ok = window.confirmDialog
                ? await window.confirmDialog({ title: 'Clear the chat?', message: 'All your questions and answers will be deleted.', variant: 'danger', confirmText: 'Clear chat' })
                : window.confirm('Clear the chat? All your questions and answers will be deleted.');
            if (!ok) return;
            try {
                var res = await fetch(clearUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('clear failed');
            } catch (err) {
                if (window.toast) window.toast('Could not clear the chat. Please try again.', 'error');
                return;
            }
            chat.innerHTML = '<div id="emptyState" class="chat-empty"><div class="empty-state" role="status">'
                + '<div class="empty-icon tone-brand"><i class="bi bi-chat-square-text" aria-hidden="true"></i></div>'
                + '<p class="empty-title">Chat cleared</p><p class="empty-desc">Ask a new question below.</p></div></div>';
            document.getElementById('historyList').innerHTML = '<div class="empty-state empty-state-quiet" role="status">'
                + '<div class="empty-icon tone-brand"><i class="bi bi-chat-square" aria-hidden="true"></i></div>'
                + '<p class="empty-title">No questions yet.</p></div>';
            clearBtn.disabled = true;
            if (window.toast) window.toast('Chat cleared.');
        });
    }
});
</script>
@endpush
