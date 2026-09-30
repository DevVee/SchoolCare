@extends('layouts.app')

@php
    $assistant = settings('ai_assistant_name') ?: 'Assistant';
    $chronological = $conversations->sortBy('created_at')->values();
    // Suggestions: clinic how-tos mixed with a few general requests, to show it can answer anything.
    $prompts = [
        'How do I add a new patient?',
        'Which medicines are low on stock?',
        'Write a parent notice',
        'How do I approve an appointment?',
        'How do I record dispensed medicine?',
        'Explain a medical term',
    ];
@endphp

@section('title', $assistant)

@section('content')
<div class="vstack gap-3">

    <x-ui.page-header :title="$assistant"
        description="Ask anything, from how to use the system to writing a parent notice or a health question. Answers can be wrong, so check anything medical against your standing orders."
        :breadcrumbs="['Dashboard' => route('dashboard'), $assistant => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="trash" id="clearBtn" :disabled="$conversations->isEmpty()">Clear chat</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="chat-layout">
        {{-- Recent questions (desktop). Clicking one scrolls to it in the chat; the trash button deletes it. --}}
        <aside class="chat-history card" aria-labelledby="chatHistoryTitle">
            <div class="chat-history-head">
                <h2 class="chat-history-title" id="chatHistoryTitle">Recent questions</h2>
                <span id="historyCount"><x-ui.count :value="$conversations->count()" /></span>
            </div>
            <div class="chat-history-list" id="historyList">
                @forelse ($conversations as $convo)
                    <div class="chat-history-row" data-convo="{{ $convo->id }}">
                        <a href="#msg-{{ $convo->id }}" class="chat-history-item" title="{{ $convo->message }}">
                            <span class="chat-history-text">{{ \Illuminate\Support\Str::limit($convo->message, 60) }}</span>
                            <span class="chat-history-time">{{ $convo->created_at->diffForHumans() }}</span>
                        </a>
                        <button type="button" class="chat-history-delete" data-delete-url="{{ route('ai-assistant.destroy', $convo) }}"
                                aria-label="Delete this question" title="Delete">
                            <x-ui.icon name="trash" />
                        </button>
                    </div>
                @empty
                    <x-ui.empty-state quiet icon="chat-square" title="No questions yet." />
                @endforelse
            </div>
        </aside>

        <section class="chat-panel card" aria-label="Chat with {{ $assistant }}">
            <div class="chat-messages" id="chatMessages" aria-live="polite" aria-relevant="additions">
                @if ($chronological->isEmpty())
                    <div id="emptyState" class="chat-empty">
                        <x-ui.empty-state icon="chat-square-text" :title="'Ask '.$assistant.' anything'"
                            description="How to do something in the system, a letter to write, a health question. Do not type private patient details." />
                    </div>
                @else
                    @foreach ($chronological as $convo)
                        <div class="msg-row is-user" id="msg-{{ $convo->id }}" data-convo="{{ $convo->id }}">
                            <div class="msg-body">
                                <div class="msg-bubble">{{ $convo->message }}</div>
                                <div class="msg-meta">You, {{ $convo->created_at->format('M j, g:i A') }}</div>
                            </div>
                        </div>
                        <div class="msg-row is-assistant" data-convo="{{ $convo->id }}">
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
                {{-- Suggestions sit right above the input, in one row that scrolls sideways on narrow screens. --}}
                <div class="chat-prompts" role="group" aria-label="Example questions">
                    @foreach ($prompts as $prompt)
                        <button type="button" class="btn btn-secondary btn-xs" data-prompt="{{ $prompt }}">{{ $prompt }}</button>
                    @endforeach
                </div>
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
            </div>
        </section>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Chat page only. Layout for the suggestion row and the delete button, and Markdown inside answers. */
    .chat-panel .chat-compose .chat-prompts {
        flex-wrap: nowrap;
        overflow-x: auto;
        margin: 0 -1rem .625rem;
        padding: 2px 1rem;
        scrollbar-width: thin;
        overscroll-behavior-x: contain;
    }
    .chat-panel .chat-prompts > * { flex: 0 0 auto; white-space: nowrap; }
    @media (hover: none) {
        .chat-panel .chat-compose .chat-prompts { scrollbar-width: none; }
        .chat-panel .chat-prompts::-webkit-scrollbar { display: none; }
    }

    .chat-history-row { position: relative; }
    .chat-history-row .chat-history-item { padding-right: 2.5rem; }
    .chat-history-delete {
        position: absolute; top: 50%; right: .375rem; transform: translateY(-50%);
        width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center;
        border: 0; background: none; padding: 0; border-radius: 6px;
        color: var(--c-muted); opacity: 0;
        transition: opacity var(--c-dur-fast, 150ms) ease-out, color var(--c-dur-fast, 150ms) ease-out;
    }
    .chat-history-row:hover .chat-history-delete,
    .chat-history-row:focus-within .chat-history-delete { opacity: 1; }
    .chat-history-delete:hover { color: var(--bs-danger, #dc3545); }
    .chat-history-delete:focus-visible { opacity: 1; outline: 3px solid rgba(var(--brand-rgb), .35); outline-offset: -1px; }
    @media (hover: none) { .chat-history-delete { opacity: 1; } }

    .chat-panel .msg-bubble > :first-child { margin-top: 0; }
    .chat-panel .msg-bubble > :last-child { margin-bottom: 0; }
    .chat-panel .msg-bubble p { margin: 0 0 .625rem; }
    .chat-panel .msg-bubble :is(h3, h4, h5, h6) { font-size: 1rem; font-weight: 700; line-height: 1.35; margin: .875rem 0 .375rem; }
    .chat-panel .msg-bubble :is(h5, h6) { font-size: .9375rem; }
    .chat-panel .msg-bubble ol { padding-left: 1.5rem; margin: .375rem 0; }
    .chat-panel .msg-bubble li > p { margin: 0; }
    .chat-panel .msg-bubble li > p + p, .chat-panel .msg-bubble li > pre { margin-top: .375rem; }
    .chat-panel .msg-bubble li > :is(ul, ol) { margin: .125rem 0; }
    .chat-panel .msg-bubble blockquote { margin: .5rem 0; padding: .125rem 0 .125rem .75rem; border-left: 3px solid var(--c-border); color: var(--c-muted); }
    .chat-panel .msg-bubble hr { margin: .75rem 0; border: 0; border-top: 1px solid var(--c-border); opacity: 1; }
    .chat-panel .msg-bubble a { color: var(--brand-600); text-decoration: underline; text-underline-offset: 2px; }
    .chat-panel .msg-bubble pre { white-space: pre; }
    .chat-panel .msg-bubble pre code { padding: 0; background: none; font-size: inherit; }
    .chat-panel .md-table { overflow-x: auto; margin: .5rem 0 .75rem; }
    .chat-panel .md-table table { border-collapse: collapse; font-size: .875rem; overflow-wrap: normal; }
    .chat-panel .md-table :is(th, td) { border: 1px solid var(--c-border); padding: .375rem .625rem; text-align: left; vertical-align: top; }
    .chat-panel .md-table th { background: var(--c-surface-2); font-weight: 600; }
</style>
@endpush

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
    var historyList = document.getElementById('historyList');
    var historyCount = document.getElementById('historyCount');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var busy = false;

    function escapeHtml(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ── Markdown ──────────────────────────────────────────────────────────
    // Safe by construction: every piece of text is HTML-escaped, only the tags
    // below are produced, and links must be http(s) or mailto.
    var LIST = /^(\s*)([-*+]|\d{1,3}[.)])\s+(.*)$/;
    var FENCE = /^\s*(`{3,}|~{3,})/;
    var HEADING = /^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/;
    var RULE = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/;
    var QUOTE = /^\s{0,3}>/;
    var TABLE_SEP = /^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/;

    function link(url, label) {
        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
    }

    function emphasis(s) {
        return s
            .replace(/\*\*(?=\S)([\s\S]*?\S)\*\*/g, '<strong>$1</strong>')
            .replace(/__(?=\S)([\s\S]*?\S)__/g, '<strong>$1</strong>')
            .replace(/(^|[^*\w])\*(?=\S)([^*\n]*?\S)\*(?![*\w])/g, '$1<em>$2</em>')
            .replace(/(^|[^_\w])_(?=\S)([^_\n]*?\S)_(?![_\w])/g, '$1<em>$2</em>')
            .replace(/~~(?=\S)([\s\S]*?\S)~~/g, '<del>$1</del>');
    }

    function inline(text) {
        var slots = [];
        function keep(html) { slots.push(html); return '\u0000' + (slots.length - 1) + '\u0000'; }
        text = String(text).replace(/\u0000/g, '');
        text = text.replace(/`([^`\n]+)`/g, function (_, code) { return keep('<code>' + escapeHtml(code) + '</code>'); });
        text = text.replace(/<br\s*\/?>/gi, function () { return keep('<br>'); });
        text = text.replace(/\[([^\]\n]+)\]\(\s*((?:https?:\/\/|mailto:)[^\s)]+)\s*\)/g, function (_, label, url) {
            return keep(link(url, emphasis(escapeHtml(label))));
        });
        text = text.replace(/\bhttps?:\/\/[^\s<>()*]+[^\s<>().,;:!?'"*]/g, function (url) {
            return keep(link(url, escapeHtml(url)));
        });
        return emphasis(escapeHtml(text)).replace(/\u0000(\d+)\u0000/g, function (_, i) { return slots[+i]; });
    }

    function cells(line) {
        return line.trim().replace(/^\|/, '').replace(/\|$/, '').replace(/\\\|/g, '\u0001')
            .split('|').map(function (c) { return c.replace(/\u0001/g, '|').trim(); });
    }

    function isTableStart(lines, i) {
        return lines[i].indexOf('|') !== -1 && i + 1 < lines.length
            && lines[i + 1].indexOf('|') !== -1 && lines[i + 1].indexOf('-') !== -1 && TABLE_SEP.test(lines[i + 1]);
    }

    function startsBlock(lines, i) {
        var l = lines[i];
        return FENCE.test(l) || HEADING.test(l) || RULE.test(l) || QUOTE.test(l) || LIST.test(l) || isTableStart(lines, i);
    }

    function table(lines, i) {
        var head = cells(lines[i]);
        var aligns = cells(lines[i + 1]).map(function (c) {
            return /^:-+:$/.test(c) ? 'center' : /-:$/.test(c) ? 'right' : '';
        });
        var cell = function (tag, text, k) {
            return '<' + tag + (aligns[k] ? ' style="text-align:' + aligns[k] + '"' : '') + '>' + inline(text || '') + '</' + tag + '>';
        };
        var html = '<div class="md-table"><table><thead><tr>';
        head.forEach(function (c, k) { html += cell('th', c, k); });
        html += '</tr></thead><tbody>';
        i += 2;
        while (i < lines.length && lines[i].trim() && lines[i].indexOf('|') !== -1) {
            var row = cells(lines[i]);
            html += '<tr>';
            for (var k = 0; k < head.length; k++) html += cell('td', row[k], k);
            html += '</tr>';
            i++;
        }
        return { html: html + '</tbody></table></div>', next: i };
    }

    function list(lines, i) {
        var items = [];
        while (i < lines.length) {
            var line = lines[i].replace(/\t/g, '    ');
            var m = line.match(LIST);
            if (m && !RULE.test(line)) {
                items.push({ indent: m[1].length, ordered: /\d/.test(m[2]), start: parseInt(m[2], 10), width: m[1].length + m[2].length + 1, lines: [m[3]] });
                i++;
                continue;
            }
            var last = items[items.length - 1];
            if (!line.trim()) {
                // A blank line ends the list unless the next line carries on with it.
                var next = lines[i + 1];
                if (next !== undefined && (LIST.test(next) || /^\s{2,}\S/.test(next))) { last.lines.push(''); i++; continue; }
                break;
            }
            if (/^\s{2,}\S/.test(line)) {
                last.lines.push(line.replace(new RegExp('^ {0,' + last.width + '}'), ''));
                i++;
                continue;
            }
            if (!startsBlock(lines, i)) { last.lines.push(line.trim()); i++; continue; }
            break;
        }

        var html = '', stack = [];
        items.forEach(function (it) {
            var tag = it.ordered ? 'ol' : 'ul';
            while (stack.length && it.indent < stack[stack.length - 1].indent) html += '</li></' + stack.pop().tag + '>';
            var top = stack[stack.length - 1];
            if (!top || it.indent > top.indent) {
                html += '<' + tag + (it.ordered && it.start > 1 ? ' start="' + it.start + '"' : '') + '>';
                stack.push({ indent: it.indent, tag: tag });
            } else {
                html += '</li>';
                if (top.tag !== tag) { html += '</' + top.tag + '><' + tag + '>'; top.tag = tag; }
            }
            html += '<li>' + blocks(it.lines);
        });
        while (stack.length) html += '</li></' + stack.pop().tag + '>';
        return { html: html, next: i };
    }

    function blocks(lines) {
        var out = [], i = 0, r;
        while (i < lines.length) {
            var line = lines[i];
            if (!line.trim()) { i++; continue; }

            var fence = line.match(FENCE);
            if (fence) {
                var code = [];
                for (i++; i < lines.length && lines[i].trim().indexOf(fence[1]) !== 0; i++) code.push(lines[i]);
                i++;
                out.push('<pre><code>' + escapeHtml(code.join('\n')) + '</code></pre>');
                continue;
            }
            var h = line.match(HEADING);
            if (h) {
                var level = Math.min(6, h[1].length + 2);
                out.push('<h' + level + '>' + inline(h[2]) + '</h' + level + '>');
                i++;
                continue;
            }
            if (RULE.test(line)) { out.push('<hr>'); i++; continue; }
            if (QUOTE.test(line)) {
                var quote = [];
                for (; i < lines.length && QUOTE.test(lines[i]); i++) quote.push(lines[i].replace(/^\s{0,3}>\s?/, ''));
                out.push('<blockquote>' + blocks(quote) + '</blockquote>');
                continue;
            }
            if (isTableStart(lines, i)) { r = table(lines, i); out.push(r.html); i = r.next; continue; }
            if (LIST.test(line)) { r = list(lines, i); out.push(r.html); i = r.next; continue; }

            var para = [line.trim()];
            for (i++; i < lines.length && lines[i].trim() && !startsBlock(lines, i); i++) para.push(lines[i].trim());
            out.push('<p>' + inline(para.join('\n')).replace(/\n/g, '<br>') + '</p>');
        }
        return out.join('');
    }

    function renderMarkdown(text) {
        return blocks(String(text).replace(/\r\n?/g, '\n').split('\n'));
    }

    document.querySelectorAll('.msg-bubble[data-markdown]').forEach(function (el) {
        el.innerHTML = renderMarkdown(el.textContent);
        el.removeAttribute('data-markdown');
    });

    // ── Chat ──────────────────────────────────────────────────────────────
    function scrollBottom() { chat.scrollTop = chat.scrollHeight; }
    scrollBottom();

    function timeNow() {
        return new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function showEmptyChat(title, desc) {
        chat.innerHTML = '<div id="emptyState" class="chat-empty"><div class="empty-state" role="status">'
            + '<div class="empty-icon tone-brand"><i class="bi bi-chat-square-text" aria-hidden="true"></i></div>'
            + '<p class="empty-title">' + escapeHtml(title) + '</p><p class="empty-desc">' + escapeHtml(desc) + '</p></div></div>';
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

    var typingTimer = null;
    function showTyping() {
        var row = document.createElement('div');
        row.className = 'msg-row is-assistant';
        row.id = 'typingRow';
        row.innerHTML = '<span class="msg-avatar tone-bg-cobi" aria-hidden="true"><i class="bi bi-chat-square-text"></i></span>'
            + '<div class="msg-body"><div class="msg-bubble msg-typing"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> '
            + '<span id="typingText">' + escapeHtml(assistantName) + ' is writing an answer</span></div></div>';
        chat.appendChild(row);
        scrollBottom();
        // Answers that need more thought or a web search can take a while.
        typingTimer = setTimeout(function () {
            var t = document.getElementById('typingText');
            if (t) t.textContent = assistantName + ' is still working on it';
        }, 8000);
    }
    function hideTyping() {
        clearTimeout(typingTimer);
        var t = document.getElementById('typingRow');
        if (t) t.remove();
    }

    // ── Recent questions ──────────────────────────────────────────────────
    function historyTotal() { return historyList.querySelectorAll('.chat-history-row').length; }

    function syncHistory() {
        var n = historyTotal();
        historyCount.innerHTML = n > 0 ? '<span class="count-chip">' + (n > 99 ? '99+' : n) + '</span>' : '';
        if (n === 0 && !historyList.querySelector('.empty-state')) {
            historyList.innerHTML = '<div class="empty-state empty-state-quiet" role="status">'
                + '<div class="empty-icon tone-brand"><i class="bi bi-chat-square" aria-hidden="true"></i></div>'
                + '<p class="empty-title">No questions yet.</p></div>';
        }
        if (clearBtn) clearBtn.disabled = n === 0 && !chat.querySelector('.msg-row');
    }

    function addHistoryItem(id, question, deleteUrl) {
        var empty = historyList.querySelector('.empty-state');
        if (empty) empty.remove();
        var row = document.createElement('div');
        row.className = 'chat-history-row';
        row.dataset.convo = id;
        var short = question.length > 60 ? question.slice(0, 60) + '...' : question;
        row.innerHTML = '<a href="#msg-' + encodeURIComponent(id) + '" class="chat-history-item" title="' + escapeHtml(question) + '">'
            + '<span class="chat-history-text">' + escapeHtml(short) + '</span>'
            + '<span class="chat-history-time">Just now</span></a>'
            + '<button type="button" class="chat-history-delete" aria-label="Delete this question" title="Delete">'
            + '<i class="bi bi-trash c-icon" aria-hidden="true"></i></button>';
        row.querySelector('.chat-history-delete').dataset.deleteUrl = deleteUrl;
        historyList.prepend(row);
        syncHistory();
    }

    historyList.addEventListener('click', async function (e) {
        var btn = e.target.closest('.chat-history-delete');
        if (!btn) return;
        var row = btn.closest('.chat-history-row');
        var id = row ? row.dataset.convo : null;
        var ok = window.confirmDialog
            ? await window.confirmDialog({ title: 'Delete this question?', message: 'The question and its answer will be deleted.', variant: 'danger', confirmText: 'Delete' })
            : window.confirm('Delete this question and its answer?');
        if (!ok) return;
        try {
            var res = await fetch(btn.dataset.deleteUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('delete failed');
        } catch (err) {
            if (window.toast) window.toast('Could not delete the question. Please try again.', 'error');
            return;
        }
        if (row) row.remove();
        chat.querySelectorAll('.msg-row[data-convo="' + CSS.escape(String(id)) + '"]').forEach(function (el) { el.remove(); });
        if (!chat.querySelector('.msg-row')) showEmptyChat('No questions left', 'Ask a new question below.');
        syncHistory();
        if (window.toast) window.toast('Question deleted.');
    });

    // ── Compose and send ──────────────────────────────────────────────────
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
        var userRow = appendMessage('user', text);
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
                var answerRow = appendMessage('assistant', data.response || '');
                if (data.id) {
                    userRow.id = 'msg-' + data.id;
                    userRow.dataset.convo = answerRow.dataset.convo = data.id;
                    if (data.delete_url) addHistoryItem(String(data.id), text, data.delete_url);
                }
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
            showEmptyChat('Chat cleared', 'Ask a new question below.');
            historyList.innerHTML = '';
            syncHistory();
            clearBtn.disabled = true;
            if (window.toast) window.toast('Chat cleared.');
        });
    }

    // ── Prefill: /ai-assistant?q=... sends the question once, then cleans the URL
    //    so a refresh does not send it again (used by the dashboard "ask" box).
    var params = new URLSearchParams(window.location.search);
    var prefill = (params.get('q') || '').trim().slice(0, 4000);
    if (params.has('q')) {
        params.delete('q');
        var rest = params.toString();
        window.history.replaceState(null, '', window.location.pathname + (rest ? '?' + rest : '') + window.location.hash);
    }
    if (prefill) {
        send(prefill);
    } else {
        syncInput();
    }
});
</script>
@endpush
