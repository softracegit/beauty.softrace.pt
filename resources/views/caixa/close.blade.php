@extends('partials.layouts.main')

@section('title', ($pageTitle ?? 'Fechar caixa').' — '.config('app.name'))

@section('css')
  @include('relatorios._styles')
  <link rel="stylesheet" href="{{ asset('template/css/cash-register.css') }}?v={{ file_exists(public_path('template/css/cash-register.css')) ? filemtime(public_path('template/css/cash-register.css')) : time() }}">
@endsection

@section('content')
  <div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
      <div class="dash-welcome-content mb-0 flex-grow-1 min-w-0">
        <h2 class="dash-welcome-title mb-0">Fechar caixa</h2>
        <p class="text-muted small mb-0 mt-1">
          Aberta {{ \App\Support\DateTimeDisplay::business($session->opened_at) }}
          · Fundo {{ number_format($session->openingFloatEur(), 2, ',', ' ') }} €
        </p>
      </div>
      <a href="{{ route('caixa.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
  </div>

  <form method="POST" action="{{ route('caixa.close') }}" class="card">
    @csrf
    <div class="card-body">
      <div class="table-responsive mb-4">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Método de pagamento</th>
              <th class="text-end">Total CRM</th>
            </tr>
          </thead>
          <tbody>
            @foreach($summary['methods'] as $row)
              <tr>
                <td>
                  {{ $row['label'] }}
                  @if($row['informational'])
                    <span class="text-muted small">(não entra no dinheiro físico)</span>
                  @endif
                </td>
                <td class="text-end text-nowrap">{{ number_format($row['amount'], 2, ',', ' ') }} €</td>
              </tr>
            @endforeach
            @if(empty($summary['methods']))
              <tr><td colspan="2" class="text-muted">Sem vendas registadas nesta sessão.</td></tr>
            @endif
          </tbody>
          <tfoot class="table-light">
            <tr class="fw-semibold">
              <td>Vendas em dinheiro</td>
              <td class="text-end text-nowrap">{{ number_format($summary['cash_sales_total'], 2, ',', ' ') }} €</td>
            </tr>
            <tr class="fw-semibold">
              <td>Dinheiro esperado na gaveta</td>
              <td class="text-end text-nowrap">{{ number_format($summary['expected_cash_in_drawer'], 2, ',', ' ') }} €</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="counted_cash" class="form-label">Dinheiro contado na gaveta (€)</label>
          <input
            type="number"
            step="0.01"
            min="0"
            class="form-control form-control-lg @error('counted_cash') is-invalid @enderror"
            id="counted_cash"
            name="counted_cash"
            value="{{ old('counted_cash') }}"
            required
            autofocus
          >
          @error('counted_cash')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <div class="form-text text-nowrap">Fundo + vendas em dinheiro = {{ number_format($summary['expected_cash_in_drawer'], 2, ',', ' ') }}&nbsp;€</div>
        </div>
        <div class="col-12">
          <label for="notes" class="form-label">Notas (opcional)</label>
          <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
      <a href="{{ route('caixa.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      <button type="submit" class="btn btn-warning">Confirmar fecho</button>
    </div>
  </form>
@endsection
