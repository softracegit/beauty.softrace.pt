<x-mail::message>
# Olá,

Recebemos um pedido de acesso a sua conta de marcacao.

O seu codigo de verificacao e:

<p style="margin: 14px 0 18px 0; font-size: 28px; font-weight: 800; letter-spacing: 0.08em; color: #111827;">
{{ $code }}
</p>

Codigo: {{ $code }}

Este codigo expira em {{ $ttlMinutes }} minutos.

Se nao pediu este codigo, ignore este email.

{{ config('app.name') }}
</x-mail::message>

