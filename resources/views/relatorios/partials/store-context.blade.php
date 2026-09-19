{{--
  Filtro de loja nos relatórios (?loja=ID|todas).
  Expects $reportStoreFilter from RelatoriosController::resolveReportStoreContext().
--}}
@php
  use App\Support\StoreContextPreference;
  $filter = $reportStoreFilter ?? null;
  $selected = $filter['store_id'] ?? null;
  if (($filter['scope'] ?? null) === StoreContextPreference::SCOPE_ALL) {
      $selected = StoreContextPreference::SCOPE_ALL;
  }
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mt-1">
  @include('partials.store-context-filter', [
      'selected' => $selected,
      'allowAll' => true,
      'allLabel' => 'Todas as lojas',
      'inputId' => $storeContextInputId ?? 'relatorio_store_context_filter',
  ])
  @if (! ($canChooseStoreContext ?? false) || ($selectableStores ?? collect())->count() <= 1)
    @php
      $relatorioLojaNome = null;
      if (($filter['store_id'] ?? null) !== null) {
          $relatorioLojaNome = ($selectableStores ?? collect())->firstWhere('id', $filter['store_id'])?->name
              ?? current_store()->tryGet()?->name;
      }
    @endphp
    @if ($relatorioLojaNome)
      <p class="text-muted small mb-0">{{ $relatorioLojaNome }}</p>
    @endif
  @endif
</div>
