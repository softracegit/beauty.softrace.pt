@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Relatórios — Caixa').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">Caixa</h2>
        <p class="text-muted small mb-0 mt-1">Histórico de aberturas e fechos — confronto de numerário.</p>
      </div>
      @if($session)
        <button type="button" class="btn btn-warning btn-sm" data-crm-cash-register-trigger="close">
          <i class="ph ph-lock me-1"></i> Fechar o dia
        </button>
      @else
        <button type="button" class="btn btn-success btn-sm" data-crm-cash-register-trigger="open">
          <i class="ph ph-lock-open me-1"></i> Abrir caixa
        </button>
      @endif
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if(session('cash_difference') !== null && abs((float) session('cash_difference')) > 0.009)
    @php $diff = (float) session('cash_difference'); @endphp
    <div class="alert alert-{{ $diff < 0 ? 'danger' : 'warning' }}">
      Diferença de dinheiro no fecho: {{ number_format($diff, 2, ',', ' ') }} €
    </div>
  @endif

  @if($session)
    <div class="card mb-4">
      <div class="card-body">
        <span class="badge bg-warning-subtle text-warning">Caixa aberta</span>
        <div class="small text-muted mt-2">
          Desde {{ \App\Support\DateTimeDisplay::business($session->opened_at) }}
          · {{ $session->openedBy?->name ?? '—' }}
          · Fundo: {{ number_format($session->openingFloatEur(), 2, ',', ' ') }} €
        </div>
        <p class="text-muted small mb-0 mt-2">
          Para ver o resumo de movimentos e fechar o dia, use <strong>Fechar o dia</strong> (navbar ou botão acima).
        </p>
      </div>
    </div>
  @else
    <div class="card mb-4 border-success-subtle">
      <div class="card-body text-center py-4">
        <p class="text-muted mb-3">Não há sessão de caixa aberta nesta loja.</p>
        <button type="button" class="btn btn-success btn-sm" data-crm-cash-register-trigger="open">
          <i class="ph ph-lock-open me-1"></i> Abrir caixa
        </button>
      </div>
    </div>
  @endif

  @if($history->isNotEmpty())
    <div class="card">
      <div class="card-header">
        <h6 class="card-title mb-0">Últimos fechos</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>Fechado</th>
              <th>Aberto por</th>
              <th class="text-end">Fundo</th>
              <th class="text-end">Contado</th>
              <th class="text-end">Diferença</th>
            </tr>
          </thead>
          <tbody>
            @foreach($history as $closed)
              @php
                $summary = $closed->closing_summary ?? [];
                $diff = (float) ($summary['cash_difference'] ?? 0);
              @endphp
              <tr>
                <td class="text-nowrap">{{ \App\Support\DateTimeDisplay::business($closed->closed_at) }}</td>
                <td>{{ $closed->openedBy?->name ?? '—' }}</td>
                <td class="text-end text-nowrap">{{ number_format($closed->openingFloatEur(), 2, ',', ' ') }} €</td>
                <td class="text-end text-nowrap">{{ number_format($closed->closingCashCountedEur() ?? 0, 2, ',', ' ') }} €</td>
                <td class="text-end text-nowrap {{ abs($diff) > 0.009 ? ($diff < 0 ? 'text-danger' : 'text-warning') : '' }}">
                  {{ number_format($diff, 2, ',', ' ') }} €
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
