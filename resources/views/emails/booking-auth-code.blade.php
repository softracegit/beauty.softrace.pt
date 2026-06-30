<x-mail::message>
# {{ __('booking.mail.auth_code_greeting') }}

{{ __('booking.mail.auth_code_intro') }}

{{ __('booking.mail.auth_code_label') }}

<p style="margin: 14px 0 18px 0; font-size: 28px; font-weight: 800; letter-spacing: 0.08em; color: #111827;">
{{ $code }}
</p>

{{ __('booking.mail.auth_code_expires', ['minutes' => $ttlMinutes]) }}

{{ __('booking.mail.auth_code_ignore') }}

@if (! empty($continueUrl))
<x-mail::button :url="$continueUrl">
{{ __('booking.mail.auth_code_continue') }}
</x-mail::button>
@endif

{{ \App\Support\StoreMailBranding::current()['footer_name'] }}
</x-mail::message>
