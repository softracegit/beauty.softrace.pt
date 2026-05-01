<x-mail::message>
# Olá,

Recebemos um pedido de acesso à sua conta de marcação.

O seu código de verificação é:

<p style="margin: 14px 0 18px 0; font-size: 28px; font-weight: 800; letter-spacing: 0.08em; color: #111827;">
{{ $code }}
</p>

Este código expira em {{ $ttlMinutes }} minutos.

Se não pediu este código, ignore este e-mail.

{{ config('app.name') }}
</x-mail::message>
