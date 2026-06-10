<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('booking.mail.verification_page_title') }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #111827; line-height: 1.5;">
    <p>{{ __('booking.mail.verification_greeting') }}</p>
    <p>{{ __('booking.mail.verification_intro') }}</p>
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; margin: 16px 0;">{{ $code }}</p>
    <p>{{ __('booking.mail.verification_expires', ['minutes' => $ttlMinutes]) }}</p>
    <p>{{ __('booking.mail.verification_ignore') }}</p>
    @if (! empty($continueUrl))
        <p style="margin-top: 20px;"><a href="{{ $continueUrl }}" style="color: #111827; font-weight: 600;">{{ __('booking.mail.verification_continue') }}</a></p>
    @endif
</body>
</html>
