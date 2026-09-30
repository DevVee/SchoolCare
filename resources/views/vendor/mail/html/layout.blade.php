@php($brand = \App\Support\MailBrand::color())
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>{{ \App\Support\MailBrand::appName() }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body,
.footer {
width: 100% !important;
}

.content-cell {
padding: 28px 22px !important;
}
}

@media only screen and (max-width: 500px) {
.button {
display: block !important;
text-align: center !important;
}
}
</style>
{{ $head ?? '' }}
</head>
<body>
@if (trim($preheader ?? '') !== '')
{{-- Inbox preview text: shown next to the subject, hidden in the email itself. --}}
<div class="preheader" style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; opacity: 0;">{{ trim($preheader) }}</div>
@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{{ $header ?? '' }}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Brand accent -->
<tr>
<td class="accent" height="4" bgcolor="{{ $brand }}" style="background-color: {{ $brand }}; height: 4px; line-height: 4px; font-size: 4px;">&nbsp;</td>
</tr>
<!-- Body content -->
<tr>
<td class="content-cell">
{{ Illuminate\Mail\Markdown::parse($slot) }}

{{ $subcopy ?? '' }}
</td>
</tr>
</table>
</td>
</tr>

{{ $footer ?? '' }}
</table>
</td>
</tr>
</table>
</body>
</html>
