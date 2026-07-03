<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Erro') — {{ config('app.name') }}</title>
    @include('partials.head-css')
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #f8f9fc 0%, #eef1f8 100%);
        }
        .error-card {
            width: 100%;
            max-width: 32rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .error-logo {
            height: 2.5rem;
            margin-bottom: 1.5rem;
        }
        .error-code {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            color: #4f46e5;
            margin-bottom: 0.75rem;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }
        .error-text {
            color: #64748b;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .error-reference {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }
        .error-reference code {
            color: #475569;
            background: #f1f5f9;
            padding: 0.15rem 0.4rem;
            border-radius: 0.35rem;
        }
    </style>
</head>
<body>
@php
    $errorReference = \App\Services\ExceptionReportService::currentReference();
@endphp
<div class="error-page">
    <div class="error-card">
        <img src="{{ asset('template/img/logo-color-icon.png') }}" alt="{{ config('app.name') }}" class="error-logo">
        <div class="error-code">@yield('code')</div>
        <h1 class="error-title">@yield('heading')</h1>
        <p class="error-text">@yield('message')</p>
        @hasSection('reference')
            @yield('reference')
        @elseif ($errorReference)
            <p class="error-reference">
                Se contactar o suporte, indique a referência
                <code>{{ $errorReference }}</code>.
            </p>
        @endif
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">Voltar</a>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">
                {{ auth()->check() ? 'Ir para o início' : 'Iniciar sessão' }}
            </a>
        </div>
    </div>
</div>
</body>
</html>
