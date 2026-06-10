<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} — {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('template/vendor/bootstrap/css/bootstrap.min.css') }}">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #f8f9fa;
            color: #212529;
        }
        .legal-page {
            max-width: 48rem;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }
        .legal-page h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .legal-page .legal-meta {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .legal-page h2 {
            font-size: 1.05rem;
            font-weight: 600;
            margin-top: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .legal-page p,
        .legal-page li {
            font-size: 0.9375rem;
            line-height: 1.65;
            color: #495057;
        }
        .legal-page ul {
            padding-left: 1.25rem;
        }
        .legal-nav {
            font-size: 0.875rem;
            border-top: 1px solid #dee2e6;
            margin-top: 2.5rem;
            padding-top: 1.25rem;
        }
        .legal-nav a {
            color: #212529;
            text-decoration: none;
        }
        .legal-nav a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main class="legal-page">
        @yield('content')

        <nav class="legal-nav d-flex flex-wrap gap-3" aria-label="Documentos legais">
            <a href="{{ route('legal.terms') }}">Termos e Condições</a>
            <a href="{{ route('legal.privacy') }}">Política de Privacidade</a>
            <a href="{{ route('legal.cookies') }}">Política de Cookies</a>
        </nav>
    </main>
</body>
</html>
