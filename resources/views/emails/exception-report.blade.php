<x-mail::message>
# Relatório de erro

**Referência:** `{{ $reference }}`  
**Aplicação:** {{ config('app.name') }} ({{ config('app.env') }})  
**Quando:** {{ now()->timezone(config('booking.business_timezone', 'Europe/Lisbon'))->format('d/m/Y H:i:s T') }}

## Exceção

- **Tipo:** `{{ $exceptionClass }}`
- **Mensagem:** {{ $exceptionMessage }}
- **Ficheiro:** `{{ $exceptionFile }}:{{ $exceptionLine }}`

@if ($url)
## Pedido

- **URL:** {{ $url }}
- **Método:** {{ $method }}
@if ($userId)
- **Utilizador:** #{{ $userId }}
@endif
@if ($storeId)
- **Loja:** #{{ $storeId }}
@endif
@if ($ip)
- **IP:** {{ $ip }}
@endif
@if ($userAgent)
- **User-Agent:** {{ $userAgent }}
@endif
@endif

@if ($input !== [])
## Dados do formulário (filtrados)

```
{{ json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
```
@endif

## Stack trace

```
{{ $stackTrace }}
```

<x-mail::subcopy>
Email automático — não responder. Use a referência para correlacionar com o pedido do utilizador.
</x-mail::subcopy>
</x-mail::message>
