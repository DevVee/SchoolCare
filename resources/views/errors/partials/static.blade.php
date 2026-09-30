{{--
    Self-contained error page for 500 and 503: no database, no settings(), no
    Vite manifest, no session. Only inline CSS and the static /sscms-icon.svg.
    Variables: $code, $title, $message, $retry (bool).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $title }}</title>
<link rel="icon" href="/sscms-icon.svg">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;background:#F8FAFC;color:#0F172A;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans",sans-serif;line-height:1.5}
main{width:100%;max-width:30rem}
img{display:block;width:40px;height:40px;border-radius:8px;margin-bottom:2rem}
.code{font-size:.8125rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#475569;margin:0 0 .5rem}
h1{font-size:1.75rem;font-weight:700;letter-spacing:-.02em;line-height:1.2;margin:0 0 .75rem}
p.msg{font-size:1rem;color:#475569;margin:0 0 1.75rem}
.actions{display:flex;flex-wrap:wrap;gap:.75rem}
a.btn{display:inline-flex;align-items:center;min-height:44px;padding:0 1.125rem;border-radius:6px;font-weight:600;font-size:.9375rem;text-decoration:none;border:1px solid #CBD5E1;background:#fff;color:#0F172A;transition:background-color .15s ease-out,transform .1s ease-out}
a.btn:hover{background:#F1F5F9}
a.btn:active{transform:scale(.97)}
a.btn-primary{background:#2563EB;border-color:#2563EB;color:#fff}
a.btn-primary:hover{background:#1D4ED8}
a.btn:focus-visible{outline:3px solid rgba(37,99,235,.45);outline-offset:2px}
@media (prefers-reduced-motion:reduce){a.btn{transition:none}}
</style>
</head>
<body>
<main>
    <img src="/sscms-icon.svg" alt="" width="40" height="40">
    <p class="code">Error {{ $code }}</p>
    <h1>{{ $title }}</h1>
    <p class="msg">{{ $message }}</p>
    <div class="actions">
        @if ($retry ?? true)
            <a class="btn btn-primary" href="">Try again</a>
        @endif
        <a class="btn" href="/dashboard">Go to dashboard</a>
    </div>
</main>
</body>
</html>
