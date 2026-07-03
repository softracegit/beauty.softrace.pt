@extends('partials.layouts.main')
@section('title', 'Teste de erros | Dev')

@section('content')
<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Teste de erros e relatórios</h5>
        <p class="text-muted mb-4">
            Rotas disponíveis apenas fora de <code>production</code>. Use para validar email e página amigável antes do deploy.
        </p>

        <dl class="row small mb-4">
            <dt class="col-sm-3">APP_ENV</dt>
            <dd class="col-sm-9"><code>{{ $appEnv }}</code></dd>
            <dt class="col-sm-3">APP_DEBUG</dt>
            <dd class="col-sm-9"><code>{{ $appDebug ? 'true' : 'false' }}</code></dd>
            <dt class="col-sm-3">ERROR_REPORT_ENABLED</dt>
            <dd class="col-sm-9"><code>{{ $reportEnabled ? 'true' : 'false' }}</code></dd>
            <dt class="col-sm-3">ERROR_REPORT_EMAIL</dt>
            <dd class="col-sm-9">
                @if ($reportRecipients === [])
                    <span class="text-warning">(vazio — usa MAIL_FROM_ADDRESS)</span>
                @else
                    {{ implode(', ', $reportRecipients) }}
                @endif
            </dd>
        </dl>

        <div class="d-flex flex-column gap-2 align-items-start">
            <a class="btn btn-outline-primary" href="{{ route('dev.error-test.email') }}" target="_blank" rel="noopener">
                1. Testar email (JSON, sem página de erro)
            </a>
            <a class="btn btn-outline-primary" href="{{ route('dev.error-test.page') }}" target="_blank" rel="noopener">
                2. Pré-visualizar página 500 (sem enviar email)
            </a>
            <a class="btn btn-outline-danger" href="{{ route('dev.error-test.throw') }}" target="_blank" rel="noopener">
                3. Fluxo completo (exceção real)
            </a>
        </div>

        <hr class="my-4">

        <h6 class="mb-2">Como testar localmente</h6>
        <ul class="small text-muted mb-0">
            <li><strong>Local:</strong> <code>APP_ENV=local</code>, <code>APP_DEBUG=true</code>, <code>ERROR_REPORT_ENABLED=true</code> — o passo 1 envia email; o passo 3 mostra stack trace Laravel.</li>
            <li><strong>Simular produção:</strong> ponha temporariamente <code>APP_DEBUG=false</code> e use o passo 3 — verá a página amigável + email.</li>
            <li><strong>Servidor:</strong> <code>APP_ENV=production</code>, <code>APP_DEBUG=false</code> — estas rotas não existem.</li>
        </ul>
    </div>
</div>
@endsection
