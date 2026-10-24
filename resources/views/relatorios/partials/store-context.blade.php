{{--
  Filtro de loja nos relatórios (?loja=ID|todas).
  Expects $reportStoreFilter from RelatoriosController::resolveReportStoreContext().
  Colocar na barra de ações (à esquerda do Exportar), não sob o título.
--}}
@php
  use App\Support\StoreContextPreference;
  $filter = $reportStoreFilter ?? null;
  $selected = $filter['store_id'] ?? null;
  if (($filter['scope'] ?? null) === StoreContextPreference::SCOPE_ALL) {
      $selected = StoreContextPreference::SCOPE_ALL;
  }
@endphp
<div class="relatorio-store-context d-flex align-items-center">
  @include('partials.store-context-filter', [
      'selected' => $selected,
      'allowAll' => true,
      'allLabel' => 'Todas as lojas',
      'inputId' => $storeContextInputId ?? 'relatorio_store_context_filter',
      'class' => 'relatorio-store-context__select',
      'style' => 'min-width: 11rem; max-width: 16rem;',
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
      <span class="relatorio-store-context__label text-muted small mb-0">{{ $relatorioLojaNome }}</span>
    @endif
  @endif
</div>
