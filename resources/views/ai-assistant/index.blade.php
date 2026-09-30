@extends('layouts.app')

@php
    $assistant = settings('ai_assistant_name') ?: 'Coco';
    $chronological = $conversations->sortBy('created_at')->values();
    // Starters: clinic how-tos mixed with general requests, to show it can answer anything.
    $prompts = [
        ['clipboard2-pulse', 'What needs attention today?'],
        ['capsule', 'Which medicines are low on stock?'],
        ['envelope-paper', 'Write a parent notice'],
        ['person-plus', 'How do I add a new patient?'],
        ['calendar-check', 'How do I approve an appointment?'],
        ['book', 'Explain a medical term'],
    ];
@endphp

@section('title', $assistant)

@section('content')
{{-- The whole page is the conversation (ServiceCo Mory): no page header card here. --}}
<section @class(['coco', 'is-empty' => $chronological->isEmpty()]) id="coco" aria-label="Chat with {{ $assistant }}">
    <header class="coco-head">
        <x-ui.coco-orb size="md" online />
        <div class="coco-head-text">
            <h1 class="coco-name">{{ $assistant }}</h1>
            <p class="coco-status"><span class="c-live" aria-hidden="true"></span>Online, answers in a few seconds</p>
        </div>
        <div class="coco-head-actions">
            <button type="button" class="btn btn-secondary btn-sm coco-history-btn" data-bs-toggle="modal" data-bs-target="#cocoHistory">
                <x-ui.icon name="clock-history" />
                <span class="d-none d-sm-inline">History</span>
                <span id="historyCount">@if ($conversations->count())<x-ui.count :value="$conversations->count()" />@endif</span>
            </button>
        </div>
    </header>

    <div class="coco-messages" id="chatMessages" role="log" aria-live="polite" aria-relevant="additions">
        <div class="coco-intro">
            <x-ui.coco-orb size="lg" />
            <p class="coco-intro-name">{{ $assistant }}</p>
            <p class="coco-intro-text">Ask anything, from using the system to a parent notice or a health question. Check anything medical against your standing orders, and leave out private patient details.</p>
        </div>
        <span class="coco-spacer" aria-hidden="true"></span>
        @foreach ($chronological as $convo)
            <div class="coco-row is-you" id="msg-{{ $convo->id }}" data-convo="{{ $convo->id }}">
                <div class="coco-msg">
                    <div class="coco-bubble">{{ $convo->message }}</div>
                    <div class="coco-time">{{ $convo->created_at->format('M j, g:i A') }}</div>
                </div>
            </div>
            <div class="coco-row is-ai" data-convo="{{ $convo->id }}">
                <x-ui.coco-orb size="xs" still />
                <div class="coco-msg">
                    <div class="coco-bubble" data-markdown>{{ $convo->response }}</div>
                    <div class="coco-time">{{ $assistant }}, {{ $convo->created_at->format('M j, g:i A') }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="coco-compose">
        <div class="coco-starters" role="group" aria-label="Example questions">
            @foreach ($prompts as [$icon, $prompt])
                <button type="button" class="coco-starter" data-prompt="{{ $prompt }}"><x-ui.icon :name="$icon" />{{ $prompt }}</button>
            @endforeach
        </div>
        <form id="chatForm" class="coco-input" autocomplete="off">
            <span class="c-ask-ring" aria-hidden="true"></span>
            <label for="chatInput" class="visually-hidden">Message {{ $assistant }}</label>
            <textarea id="chatInput" rows="1" maxlength="4000" placeholder="Ask {{ $assistant }} anything"></textarea>
            <button type="submit" class="coco-send" id="sendBtn" aria-label="Send to {{ $assistant }}" disabled><x-ui.icon name="arrow-up" /></button>
        </form>
        <p class="coco-foot">
            <span class="coco-foot-keys">Enter to send, Shift and Enter for a new line.</span>
            <span class="tabular" id="charCount" hidden></span>
        </p>
    </div>
</section>

<template id="cocoFace"><x-ui.coco-orb size="xs" still /></template>
@endsection

@push('modals')
<x-ui.modal id="cocoHistory" title="Your questions" subtitle="Pick one to jump to it in the chat." sheet>
    <div class="coco-history" id="historyList">
        @forelse ($conversations as $convo)
            <div class="coco-history-row" data-convo="{{ $convo->id }}">
                <button type="button" class="coco-history-item" data-jump="{{ $convo->id }}" title="{{ $convo->message }}">
                    <x-ui.icon name="chat" />
                    <span class="coco-history-text">
                        <span class="coco-history-q">{{ \Illuminate\Support\Str::limit($convo->message, 80) }}</span>
                        <span class="coco-history-time">{{ $convo->created_at->diffForHumans() }}</span>
                    </span>
                </button>
                <button type="button" class="coco-history-delete" data-delete-url="{{ route('ai-assistant.destroy', $convo) }}" aria-label="Delete this question" title="Delete">
                    <x-ui.icon name="trash" />
                </button>
            </div>
        @empty
            <p class="coco-history-empty">No questions yet. Ask {{ $assistant }} anything below.</p>
        @endforelse
    </div>
    <x-slot:footer>
        <button type="button" class="btn btn-ghost text-danger me-auto" id="clearBtn" @disabled($conversations->isEmpty())>
            <x-ui.icon name="trash" />Clear all
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
    </x-slot:footer>
</x-ui.modal>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var assistantName = @json($assistant);
    var chatUrl = @json(route('ai-assistant.chat'));
    var clearUrl = @json(route('ai-assistant.clear'));
    var root = document.getElementById('coco');
    var chat = document.getElementById('chatMessages');
    var form = document.getElementById('chatForm');
    var input = document.getElementById('chatInput');
    var sendBtn = document.getElementById('sendBtn');
    var clearBtn = document.getElementById('clearBtn');
    var charCount = document.getElementById('charCount');
    var historyList = document.getElementById('historyList');
    var historyCount = document.getElementById('historyCount');
    var historyModal = document.getElementById('cocoHistory');
    var face = document.getElementById('cocoFace');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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

    document.querySelectorAll('.coco-bubble[data-markdown]').forEach(function (el) {
        el.innerHTML = renderMarkdown(el.textContent);
        el.removeAttribute('data-markdown');
    });

    // ── Chat ──────────────────────────────────────────────────────────────
    // The page scrolls (not a box inside it), so new messages bring the window down.
    function scrollBottom(smooth) {
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: smooth && !reduceMotion ? 'smooth' : 'auto' });
    }
    if (chat.querySelector('.coco-row')) scrollBottom(false);

    function timeNow() {
        return new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function syncEmpty() {
        root.classList.toggle('is-empty', !chat.querySelector('.coco-row'));
    }

    function appendMessage(role, content, opts) {
        opts = opts || {};
        var you = role === 'user';
        var row = document.createElement('div');
        row.className = 'coco-row ' + (you ? 'is-you' : 'is-ai') + (opts.error ? ' is-error' : '');
        var body = you ? escapeHtml(content).replace(/\n/g, '<br>') : renderMarkdown(content);
        row.innerHTML = '<div class="coco-msg"><div class="coco-bubble">' + body + '</div>'
            + '<div class="coco-time">' + escapeHtml((you ? '' : assistantName + ', ') + timeNow()) + '</div></div>';
        if (!you) row.prepend(face.content.cloneNode(true));
        chat.appendChild(row);
        syncEmpty();
        scrollBottom(true);
        return row;
    }

    var typingTimer = null;
    function showTyping() {
        var row = document.createElement('div');
        row.className = 'coco-row is-ai';
        row.id = 'typingRow';
        row.setAttribute('aria-label', assistantName + ' is writing an answer');
        row.innerHTML = '<div class="coco-msg"><div class="coco-bubble coco-typing"><span></span><span></span><span></span></div></div>';
        row.prepend(face.content.cloneNode(true));
        chat.appendChild(row);
        scrollBottom(true);
        // Answers that need more thought can take a while: say so, calmly.
        typingTimer = setTimeout(function () {
            var bubble = row.querySelector('.coco-typing');
            if (bubble) bubble.insertAdjacentHTML('beforeend', '<span class="coco-typing-text">Still working on it</span>');
        }, 8000);
    }
    function hideTyping() {
        clearTimeout(typingTimer);
        var t = document.getElementById('typingRow');
        if (t) t.remove();
    }

    // ── History (modal) ───────────────────────────────────────────────────
    function historyTotal() { return historyList.querySelectorAll('.coco-history-row').length; }

    function syncHistory() {
        var n = historyTotal();
        historyCount.innerHTML = n > 0 ? '<span class="count-chip">' + (n > 99 ? '99+' : n) + '</span>' : '';
        if (n === 0 && !historyList.querySelector('.coco-history-empty')) {
            historyList.innerHTML = '<p class="coco-history-empty">No questions yet. Ask ' + escapeHtml(assistantName) + ' anything below.</p>';
        }
        if (clearBtn) clearBtn.disabled = n === 0 && !chat.querySelector('.coco-row');
    }

    function addHistoryItem(id, question, deleteUrl) {
        var empty = historyList.querySelector('.coco-history-empty');
        if (empty) empty.remove();
        var row = document.createElement('div');
        row.className = 'coco-history-row';
        row.dataset.convo = id;
        var short = question.length > 80 ? question.slice(0, 80) + '...' : question;
        row.innerHTML = '<button type="button" class="coco-history-item" title="' + escapeHtml(question) + '">'
            + '<i class="bi bi-chat c-icon" aria-hidden="true"></i><span class="coco-history-text">'
            + '<span class="coco-history-q">' + escapeHtml(short) + '</span>'
            + '<span class="coco-history-time">Just now</span></span></button>'
            + '<button type="button" class="coco-history-delete" aria-label="Delete this question" title="Delete">'
            + '<i class="bi bi-trash c-icon" aria-hidden="true"></i></button>';
        row.querySelector('.coco-history-item').dataset.jump = id;
        row.querySelector('.coco-history-delete').dataset.deleteUrl = deleteUrl;
        historyList.prepend(row);
        syncHistory();
    }

    function closeHistory(then) {
        var modal = window.bootstrap ? window.bootstrap.Modal.getInstance(historyModal) : null;
        if (!modal || !historyModal.classList.contains('show')) { if (then) then(); return; }
        if (then) historyModal.addEventListener('hidden.bs.modal', then, { once: true });
        modal.hide();
    }

    historyList.addEventListener('click', async function (e) {
        var jump = e.target.closest('[data-jump]');
        if (jump) {
            var target = document.getElementById('msg-' + jump.dataset.jump);
            closeHistory(function () {
                if (!target) return;
                target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
                target.classList.add('is-flash');
                setTimeout(function () { target.classList.remove('is-flash'); }, 1600);
            });
            return;
        }
        var btn = e.target.closest('.coco-history-delete');
        if (!btn) return;
        var row = btn.closest('.coco-history-row');
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
        chat.querySelectorAll('.coco-row[data-convo="' + CSS.escape(String(id)) + '"]').forEach(function (el) { el.remove(); });
        syncEmpty();
        syncHistory();
        if (window.toast) window.toast('Question deleted.');
    });

    // ── Compose and send ──────────────────────────────────────────────────
    function syncInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        sendBtn.disabled = busy || !input.value.trim();
        var n = input.value.length;
        charCount.hidden = n < 3500;
        charCount.textContent = n + ' / 4000';
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
        document.querySelectorAll('[data-prompt]').forEach(function (b) { b.disabled = true; });
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
            appendMessage('assistant', 'Could not reach ' + assistantName + '. Check your internet connection and try again.', { error: true });
        } finally {
            busy = false;
            document.querySelectorAll('[data-prompt]').forEach(function (b) { b.disabled = false; });
            syncInput();
            syncHistory();
            input.focus();
        }
    }

    document.querySelectorAll('[data-prompt]').forEach(function (btn) {
        btn.addEventListener('click', function () { send(btn.dataset.prompt); });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', async function () {
            var ok = window.confirmDialog
                ? await window.confirmDialog({ title: 'Clear the chat?', message: 'All your questions and answers will be deleted.', variant: 'danger', confirmText: 'Clear all' })
                : window.confirm('Clear the chat? All your questions and answers will be deleted.');
            if (!ok) return;
            try {
                var res = await fetch(clearUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('clear failed');
            } catch (err) {
                if (window.toast) window.toast('Could not clear the chat. Please try again.', 'error');
                return;
            }
            chat.querySelectorAll('.coco-row').forEach(function (el) { el.remove(); });
            historyList.innerHTML = '';
            syncEmpty();
            syncHistory();
            closeHistory();
            if (window.toast) window.toast('Chat cleared.');
        });
    }

    // ── Prefill: /ai-assistant?q=... sends the question once, then cleans the URL
    //    so a refresh does not send it again (used by the dashboard ask box).
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
        if (window.matchMedia('(min-width: 768px)').matches) input.focus({ preventScroll: true });
    }
});
</script>
@endpush
