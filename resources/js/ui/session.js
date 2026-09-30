/**
 * Session keep-alive + CSRF token refresh.
 *
 * Only runs on pages that declare <meta name="session-keepalive" content="URL">
 * (the signed-in app layout). Every 20 minutes it calls the endpoint, which:
 *   (a) resets the server-side session lifetime while a tab is open but idle;
 *   (b) returns a fresh CSRF token that is written into
 *       <meta name="csrf-token">, axios defaults and every form[_token] field,
 *       so long-lived tabs do not fail with "page expired" (419) on submit.
 * If the session already ended, the endpoint redirects to the login page and
 * the JSON parse fails: that is ignored, the user meets the login page on the
 * next navigation.
 */
const INTERVAL_MS = 20 * 60 * 1000;

const endpointMeta = document.querySelector('meta[name="session-keepalive"]');

function applyToken(token) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.content = token;
    if (window.axios) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    document.querySelectorAll('input[name="_token"]').forEach((input) => {
        input.value = token;
    });
}

function refresh(url) {
    return fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
    })
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
            if (data && data.token) applyToken(data.token);
        })
        .catch(() => {});
}

if (endpointMeta && endpointMeta.content) {
    const url = endpointMeta.content;
    setInterval(() => refresh(url), INTERVAL_MS);

    // A laptop waking from sleep may have missed several ticks: refresh when
    // the tab becomes visible again after a long gap.
    let hiddenAt = 0;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            hiddenAt = Date.now();
        } else if (hiddenAt && Date.now() - hiddenAt > INTERVAL_MS) {
            refresh(url);
        }
    });
}

export { refresh as refreshSessionToken };
