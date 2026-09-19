{{-- Preserva ?loja= ao submeter outros filtros GET do relatório. --}}
@php
  use App\Support\StoreContextPreference;
  $filter = $reportStoreFilter ?? null;
  $lojaValue = (($filter['scope'] ?? null) === StoreContextPreference::SCOPE_ALL || ($filter['store_id'] ?? null) === null)
      ? StoreContextPreference::SCOPE_ALL
      : (string) $filter['store_id'];
@endphp
@if ($filter !== null)
  <input type="hidden" name="{{ StoreContextPreference::QUERY_STORE }}" value="{{ $lojaValue }}">
@endif
