/**
 * Show / hide password: <button data-password-toggle="inputId" aria-pressed="false">.
 * Swaps the input type, the eye icon and the accessible label. Delegated, so
 * it works for fields added later (modals).
 */
document.addEventListener('click', (e) => {
    const btn = e.target instanceof Element ? e.target.closest('[data-password-toggle]') : null;
    if (!btn) return;
    const input = document.getElementById(btn.getAttribute('data-password-toggle'));
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    const icon = btn.querySelector('.bi');
    if (icon) {
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
    }
});
