@php
  $fieldIdPrefix = $fieldIdPrefix ?? 'pt_';
  $showActive = (bool) ($showActive ?? false);
  $icons = $icons ?? [];
  $currentIcon = $icon ?? 'ph-clock';
@endphp

@once
<style>
  .pt-icon-picker {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(2.5rem, 1fr));
    gap: .4rem;
    max-height: 11.5rem;
    overflow-y: auto;
    padding: .35rem;
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem;
    background: var(--bs-body-bg);
  }
  .pt-icon-option {
    margin: 0;
    cursor: pointer;
  }
  .pt-icon-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }
  .pt-icon-option__face {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: .4rem;
    border: 1px solid transparent;
    color: var(--bs-body-color);
    transition: background-color .12s ease, border-color .12s ease, color .12s ease;
  }
  .pt-icon-option__face i {
    font-size: 1.25rem;
    line-height: 1;
  }
  .pt-icon-option:hover .pt-icon-option__face {
    background: var(--bs-secondary-bg);
  }
  .pt-icon-option input:checked + .pt-icon-option__face {
    border-color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .1);
    color: var(--bs-primary);
  }
  .pt-icon-option input:focus-visible + .pt-icon-option__face {
    outline: 2px solid var(--bs-primary);
    outline-offset: 1px;
  }
</style>
@endonce

<div class="mb-3">
  <label class="form-label" for="{{ $fieldIdPrefix }}name">Nome</label>
  <input type="text" class="form-control" name="name" id="{{ $fieldIdPrefix }}name"
    value="{{ $name ?? '' }}" required maxlength="80" placeholder="Ex.: Refeição">
  <div class="form-text">Não use «Outro» — esse tipo é fixo na agenda.</div>
</div>

<div class="mb-3">
  <div class="form-label" id="{{ $fieldIdPrefix }}icon_label">Ícone</div>
  <div class="pt-icon-picker" role="radiogroup" aria-labelledby="{{ $fieldIdPrefix }}icon_label" data-pt-icon-picker>
    @foreach($icons as $opt)
      <label class="pt-icon-option" title="{{ $opt['label'] }}">
        <input type="radio"
          name="icon"
          value="{{ $opt['value'] }}"
          id="{{ $fieldIdPrefix }}icon_{{ $loop->index }}"
          @checked($currentIcon === $opt['value'])
          @required($loop->first)
          data-pt-icon-option>
        <span class="pt-icon-option__face">
          <i class="ph {{ $opt['value'] }}" aria-hidden="true"></i>
        </span>
        <span class="visually-hidden">{{ $opt['label'] }}</span>
      </label>
    @endforeach
  </div>
</div>

<div class="mb-3">
  <label class="form-label" for="{{ $fieldIdPrefix }}duration">Duração predefinida (minutos)</label>
  <input type="number" class="form-control" name="duration" id="{{ $fieldIdPrefix }}duration"
    value="{{ $duration ?? 60 }}" required min="5" max="480" step="5">
  <div class="form-text">Usada na agenda para calcular o fim ao criar um tempo pessoal deste tipo.</div>
</div>

@if ($showActive)
  <div class="form-check">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="{{ $fieldIdPrefix }}is_active"
      @checked($isActive ?? true)>
    <label class="form-check-label" for="{{ $fieldIdPrefix }}is_active">Activo na agenda</label>
  </div>
@endif
