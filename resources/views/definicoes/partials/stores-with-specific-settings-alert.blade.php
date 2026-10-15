{{--
  storesWithSpecificSettings: list<array{id:int,name:string}>
  storesWithSpecificSettingsTab: string|null — tab na ficha da loja (ex.: booking)
--}}
@php
  $specificStores = $storesWithSpecificSettings ?? [];
  $specificTab = $storesWithSpecificSettingsTab ?? null;
@endphp
@if (is_array($specificStores) && $specificStores !== [])
  <div class="alert alert-warning border-warning-subtle mb-3" role="status">
    <div class="d-flex gap-2 align-items-start">
      <i class="ph ph-warning-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
      <div class="flex-grow-1">
        <div class="fw-semibold mb-1">Lojas com definições específicas</div>
        <p class="small mb-2 mb-md-3">
          Ao guardar, estas lojas <strong>não serão alteradas</strong> — mantêm as definições da respectiva ficha.
          Para as actualizar, edite cada loja em Lojas.
        </p>
        <ul class="mb-0 small ps-3">
          @foreach ($specificStores as $row)
            @php
              $lojaParams = ['loja' => $row['id']];
              if (is_string($specificTab) && $specificTab !== '') {
                $lojaParams['tab'] = $specificTab;
              }
            @endphp
            <li class="mb-1">
              <a href="{{ route('lojas.edit', $lojaParams) }}" class="fw-semibold link-dark">{{ $row['name'] }}</a>
              <span class="text-muted"> — alterar na ficha da loja</span>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
@endif
