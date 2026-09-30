/**
 * Email sign-in code page (resources/views/auth/sign-in-code.blade.php).
 *
 * [data-otp-form]: six one-digit boxes ([data-otp-box]) replace the single
 * `code` input, which becomes hidden and receives the digits. Typing moves to
 * the next box, Backspace on an empty box goes back, arrow keys move, and a
 * pasted or autofilled code fills every box. An incomplete code is not sent.
 * Without JavaScript the single input stays visible and works on its own.
 *
 * [data-otp-resend][data-wait="45"]: "Resend code" stays disabled with a
 * countdown until another code may be sent (the server checks it as well).
 */
const LENGTH = 6;

function initCode(form) {
    const single = form.querySelector('input[name="code"]');
    const group = form.querySelector('[data-otp-boxes]');
    const boxes = group ? Array.from(group.querySelectorAll('[data-otp-box]')) : [];
    if (!single || boxes.length !== LENGTH) return;

    const describedBy = single.getAttribute('aria-describedby');
    const hadFocus = document.activeElement === single || single.hasAttribute('autofocus');
    single.removeAttribute('autofocus');
    single.removeAttribute('required');
    single.type = 'hidden';
    single.value = '';
    group.hidden = false;
    boxes.forEach((box) => {
        if (describedBy) box.setAttribute('aria-describedby', describedBy);
        if (single.getAttribute('aria-invalid') === 'true') box.setAttribute('aria-invalid', 'true');
    });

    const sync = () => {
        single.value = boxes.map((b) => b.value).join('');
    };
    const focusBox = (i) => {
        const box = boxes[Math.max(0, Math.min(LENGTH - 1, i))];
        box.focus();
        box.select();
    };
    // Put digits into the boxes from `start` on (a whole code always starts at the first box).
    const fill = (start, digits) => {
        const from = digits.length >= LENGTH ? 0 : start;
        let i = from;
        for (const d of digits.slice(0, LENGTH - from)) {
            boxes[i].value = d;
            i += 1;
        }
        sync();
        focusBox(i >= LENGTH ? LENGTH - 1 : i);
    };

    boxes.forEach((box, i) => {
        box.addEventListener('keydown', (e) => {
            if (e.isComposing || e.ctrlKey || e.metaKey || e.altKey) return;
            if (/^[0-9]$/.test(e.key)) {
                // Replace whatever the box holds and move on.
                e.preventDefault();
                box.value = e.key;
                sync();
                if (i < LENGTH - 1) focusBox(i + 1);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                if (box.value !== '') {
                    box.value = '';
                } else if (i > 0) {
                    boxes[i - 1].value = '';
                    focusBox(i - 1);
                }
                sync();
            } else if (e.key === 'Delete') {
                e.preventDefault();
                box.value = '';
                sync();
            } else if (e.key === 'ArrowLeft' && i > 0) {
                e.preventDefault();
                focusBox(i - 1);
            } else if (e.key === 'ArrowRight' && i < LENGTH - 1) {
                e.preventDefault();
                focusBox(i + 1);
            } else if (e.key.length === 1 && e.key !== ' ') {
                e.preventDefault(); // letters and symbols are ignored
            }
        });

        // Mobile keyboards, autofill ("one-time-code") and anything keydown did not handle.
        box.addEventListener('input', () => {
            const digits = box.value.replace(/\D/g, '');
            if (digits.length <= 1) {
                box.value = digits;
                sync();
                if (digits && i < LENGTH - 1) focusBox(i + 1);
                return;
            }
            box.value = '';
            fill(i, digits);
        });

        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
            const digits = text.replace(/\D/g, '');
            if (digits) fill(i, digits);
        });

        box.addEventListener('focus', () => box.select());
    });

    form.addEventListener('submit', (e) => {
        sync();
        if (single.value.length !== LENGTH) {
            e.preventDefault();
            const empty = boxes.findIndex((b) => b.value === '');
            focusBox(empty === -1 ? 0 : empty);
        }
    });

    const active = document.activeElement;
    if (hadFocus || !active || active === document.body || active === single) {
        focusBox(0);
    }
}

function initResend(button) {
    const seconds = parseInt(button.dataset.wait || '0', 10);
    if (!(seconds > 0)) return;

    const label = button.textContent.trim();
    const until = Date.now() + seconds * 1000;
    let timer = null;

    const tick = () => {
        const left = Math.ceil((until - Date.now()) / 1000);
        if (left <= 0) {
            clearInterval(timer);
            button.disabled = false;
            button.textContent = label;
            return;
        }
        button.disabled = true;
        button.textContent = `${label} in ${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
    };

    tick();
    timer = setInterval(tick, 1000);
}

function init() {
    document.querySelectorAll('[data-otp-form]').forEach(initCode);
    document.querySelectorAll('[data-otp-resend]').forEach(initResend);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
