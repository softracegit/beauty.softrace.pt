@php
  $metaSmallCols = ($showClienteColumn ?? true) ? 3 : 2;
@endphp
<style>
  .table-striped > tbody > tr:nth-of-type(odd) > * {
    background-color: rgb(241, 247, 255) !important;
  }
  .vendas-report-table th,
  .vendas-report-table td {
    vertical-align: top;
  }
  .vendas-two-line { line-height: 1.15; }
  .vendas-two-line small { color: var(--bs-secondary-color); font-size: 0.75em; }
  .vendas-report-table thead th:nth-child(-n+{{ $metaSmallCols }}),
  .vendas-report-table tbody td:nth-child(-n+{{ $metaSmallCols }}) {
    font-size: 0.8125rem;
  }
  .vendas-servico-cell {
    font-size: 0.75rem;
    line-height: 1.2;
  }
  .vendas-servico-cell .vendas-servico-sub {
    font-size: 0.6875rem;
  }
</style>
