@php
  /** @var \Illuminate\Support\Collection<int, \App\Models\PersonalTimeType> $personalTimeTypes */
  $icons = $personalTimeTypeIcons ?? [];
  $section = $section ?? 'all'; // list | modals | all
@endphp

@if ($section === 'list' || $section === 'all')
@once
<style>
  .pt-types-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .65rem;
  }
  @media (min-width: 576px) {
    .pt-types-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (min-width: 992px) {
    .pt-types-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }
  .pt-type-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .3rem;
    min-height: 7.25rem;
    padding: 1.1rem .65rem .85rem;
    text-align: center;
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem;
    background: var(--bs-body-bg);
    transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
  }
  .pt-type-card:hover {
    border-color: rgba(var(--bs-primary-rgb), .45);
    background-color: rgba(var(--bs-primary-rgb), .04);
  }
  .pt-type-card.is-inactive {
    opacity: .55;
  }
  .pt-type-card-icon {
    font-size: 1.35rem;
    line-height: 1;
    color: var(--bs-body-color);
  }
  .pt-type-card-name {
    font-size: .875rem;
    font-weight: 600;
    line-height: 1.2;
  }
  .pt-type-card-duration {
    font-size: .75rem;
    color: var(--bs-secondary-color);
  }
  .pt-type-card-badge {
    position: absolute;
    top: .4rem;
    left: .45rem;
    font-size: .65rem;
    font-weight: 600;
    line-height: 1;
    padding: .2rem .4rem;
    border-radius: .3rem;
  }
  .pt-type-card-actions {
    position: absolute;
    top: .3rem;
    right: .3rem;
    display: flex;
    gap: .15rem;
    opacity: 0;
    transition: opacity .15s ease;
  }
  .pt-type-card:hover .pt-type-card-actions,
  .pt-type-card:focus-within .pt-type-card-actions {
    opacity: 1;
  }
  @media (hover: none) {
    .pt-type-card-actions {
      opacity: 1;
    }
  }
  .pt-type-card-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.7rem;
    height: 1.7rem;
    padding: 0;
    border: 0;
    border-radius: .35rem;
    background: var(--bs-body-bg);
    color: var(--bs-secondary-color);
    box-shadow: 0 0 0 1px var(--bs-border-color);
    transition: color .12s ease, background-color .12s ease;
  }
  .pt-type-card-action:hover {
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .1);
  }
  .pt-type-card-action.is-danger:hover {
    color: var(--bs-danger);
    background: rgba(var(--bs-danger-rgb), .1);
  }
  .pt-type-card-add {
    border-style: dashed;
    color: var(--bs-secondary-color);
    cursor: pointer;
    width: 100%;
  }
  .pt-type-card-add:hover {
    color: var(--bs-primary);
    border-color: var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), .06);
  }
  .pt-type-card-add-icon {
    font-size: 1.5rem;
    line-height: 1;
  }
  .pt-types-empty {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--bs-secondary-color);
    font-size: .875rem;
    padding: .5rem 0 .25rem;
  }
</style>
@endonce

<div class="d-flex align-items-center justify-content-between gap-2 mb-3">
  <h6 class="fw-semibold mb-0">Tipos</h6>
</div>

<div class="pt-types-grid">
  @forelse($personalTimeTypes as $type)
    <div class="pt-type-card {{ $type->is_active ? '' : 'is-inactive' }}">
      @unless($type->is_active)
        <span class="pt-type-card-badge bg-secondary-light text-secondary">Inactivo</span>
      @endunless

      <div class="pt-type-card-actions">
        <button type="button"
          class="pt-type-card-action"
          title="Editar"
          aria-label="Editar {{ $type->name }}"
          data-bs-toggle="modal"
          data-bs-target="#personalTimeTypeEditModal"
          data-id="{{ $type->id }}"
          data-name="{{ $type->name }}"
          data-icon="{{ $type->icon }}"
          data-duration="{{ $type->duration }}"
          data-active="{{ $type->is_active ? '1' : '0' }}"
          data-action="{{ route('definicoes.tempo-pessoal.tipos.update', $type) }}">
          <i class="ph ph-pencil-simple" aria-hidden="true"></i>
        </button>
        <button type="submit"
          class="pt-type-card-action is-danger"
          title="Eliminar"
          aria-label="Eliminar {{ $type->name }}"
          form="pt-destroy-{{ $type->id }}"
          onclick="return confirm('Eliminar ou desactivar este tipo?');">
          <i class="ph ph-trash" aria-hidden="true"></i>
        </button>
      </div>

      <i class="ph {{ $type->icon }} pt-type-card-icon" aria-hidden="true"></i>
      <span class="pt-type-card-name">{{ $type->name }}</span>
      <span class="pt-type-card-duration">{{ $type->formatted_duration }}</span>
    </div>
  @empty
    <p class="pt-types-empty mb-0">Ainda não há tipos. Crie o primeiro — «Outro» continua sempre disponível na agenda.</p>
  @endforelse

  <button type="button"
    class="pt-type-card pt-type-card-add"
    data-bs-toggle="modal"
    data-bs-target="#personalTimeTypeCreateModal">
    <i class="ph ph-plus pt-type-card-add-icon" aria-hidden="true"></i>
    <span class="pt-type-card-name">Novo tipo</span>
  </button>
</div>
@endif

@if ($section === 'modals' || $section === 'all')
  @foreach($personalTimeTypes as $type)
    <form id="pt-destroy-{{ $type->id }}"
      method="post"
      action="{{ route('definicoes.tempo-pessoal.tipos.destroy', $type) }}"
      class="d-none">
      @csrf
      @method('DELETE')
    </form>
  @endforeach

  {{-- Modal criar --}}
  <div class="modal fade" id="personalTimeTypeCreateModal" tabindex="-1" aria-labelledby="personalTimeTypeCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" action="{{ route('definicoes.tempo-pessoal.tipos.store') }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="personalTimeTypeCreateModalLabel">Novo tipo de tempo pessoal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          @include('definicoes.partials.personal-time-type-form-fields', [
            'icons' => $icons,
            'name' => old('name'),
            'icon' => old('icon', 'ph-clock'),
            'duration' => old('duration', 60),
            'isActive' => true,
            'showActive' => false,
            'fieldIdPrefix' => 'pt_create_',
          ])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Criar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal editar --}}
  <div class="modal fade" id="personalTimeTypeEditModal" tabindex="-1" aria-labelledby="personalTimeTypeEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="post" action="#" class="modal-content" id="personalTimeTypeEditForm">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title" id="personalTimeTypeEditModalLabel">Editar tipo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          @include('definicoes.partials.personal-time-type-form-fields', [
            'icons' => $icons,
            'name' => '',
            'icon' => 'ph-clock',
            'duration' => 60,
            'isActive' => true,
            'showActive' => true,
            'fieldIdPrefix' => 'pt_edit_',
          ])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
@endif
