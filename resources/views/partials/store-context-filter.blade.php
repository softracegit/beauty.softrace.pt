{{--
  Filtro de loja (contexto operacional) — GET ?loja=
  Props:
    - selected: int|null — id da loja, ou null se "todas"
    - allowAll: bool
    - allLabel: string
    - stores: Collection|null
    - formId: string|null — submete form existente; senão cria form GET próprio
    - inputId: string|null
    - class: string|null
--}}
@php
  use App\Support\StoreContextPreference;
  $name = StoreContextPreference::QUERY_STORE;
  $allowAll = (bool) ($allowAll ?? false);
  $allLabel = $allLabel ?? 'Todas as lojas';
  $stores = $stores ?? ($selectableStores ?? collect());
  $selected = $selected ?? null;
  $formId = $formId ?? null;
  $selectClass = trim('form-select form-select-sm store-context-filter '.($class ?? ''));
  $canChoose = ($canChooseStoreContext ?? false) && ($stores->count() > 1 || $allowAll);
  $selectedIsAll = $allowAll && ($selected === null || $selected === StoreContextPreference::SCOPE_ALL);
  $selectStyle = $style ?? 'min-width: 11rem; max-width: 16rem;';
@endphp

@if ($canChoose)
  @unless ($formId)
    <form method="GET" action="{{ url()->current() }}" class="store-context-filter-form d-inline-flex align-items-center m-0">
      @foreach (request()->except([$name, 'page']) as $k => $v)
        @if (is_scalar($v))
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
      @endforeach
  @endunless
      <label class="visually-hidden" for="{{ $inputId ?? 'store_context_filter' }}">Loja</label>
      <select
        name="{{ $name }}"
        id="{{ $inputId ?? 'store_context_filter' }}"
        class="{{ $selectClass }}"
        @if ($selectStyle !== '') style="{{ $selectStyle }}" @endif
        @if ($formId)
          form="{{ $formId }}"
          onchange="document.getElementById(@json($formId))?.requestSubmit?.() || document.getElementById(@json($formId))?.submit()"
        @else
          onchange="this.form.submit()"
        @endif
      >
        @if ($allowAll)
          <option value="{{ StoreContextPreference::SCOPE_ALL }}" @selected($selectedIsAll)>{{ $allLabel }}</option>
        @endif
        @foreach ($stores as $store)
          <option value="{{ $store->id }}" @selected(! $selectedIsAll && (int) $selected === (int) $store->id)>
            {{ $store->name }}
          </option>
        @endforeach
      </select>
  @unless ($formId)
    </form>
  @endunless
@endif
