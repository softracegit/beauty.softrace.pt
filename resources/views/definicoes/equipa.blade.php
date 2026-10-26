@extends('definicoes.layout')

@section('definicoes_content')
  <form method="post" action="{{ route('definicoes.equipa.update') }}" id="definicoes-equipa-form">
    @csrf

    <div class="card mb-4">
      <div class="card-body">
        @include('stores.partials.operational-equipa-fields', [
          'agents' => $agents ?? collect(),
          'roles' => $roles ?? [],
          'showStoreColumn' => $showStoreColumn ?? true,
          'idPrefix' => 'def_',
          'showPersonalTimeSection' => false,
        ])
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="uedit-section-title mb-1">Tempo pessoal</div>
        <p class="text-muted small mb-4">
          Tipos partilhados por todas as lojas da organização. O tipo <strong>Outro</strong> está sempre disponível na agenda (título livre).
        </p>

        <div class="mb-4 pb-4 border-bottom">
          <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="fw-semibold mb-0" for="def_personal_time_limit_store_hours" style="cursor: pointer">
              Limitar o tempo pessoal ao horário da loja
            </label>
            <div class="form-check form-switch m-0 flex-shrink-0">
              <input type="hidden" name="personal_time_limit_store_hours" value="0">
              <input class="form-check-input" type="checkbox" name="personal_time_limit_store_hours" value="1"
                id="def_personal_time_limit_store_hours"
                @checked((bool) old('personal_time_limit_store_hours', $personalTimeLimitStoreHours ?? false))>
            </div>
          </div>
          <p class="small text-muted mb-0 mt-2">
            Quando ligado, só aparecem horas dentro do horário habitual da loja
            (atualmente {{ $storeHoursLabel ?? '09:00–20:00' }}).
          </p>
        </div>

        @include('definicoes.partials.personal-time-types', [
          'personalTimeTypes' => $personalTimeTypes ?? collect(),
          'personalTimeTypeIcons' => $personalTimeTypeIcons ?? [],
          'section' => 'list',
        ])
      </div>
    </div>

    @include('definicoes.partials.org-save-actions', [
      'storesWithSpecificSettings' => $storesWithSpecificSettings ?? [],
      'storesWithSpecificSettingsTab' => $storesWithSpecificSettingsTab ?? 'equipa',
      'formId' => 'definicoes-equipa-form',
      'primaryLabel' => 'Guardar alterações',
    ])
  </form>

  @include('definicoes.partials.personal-time-types', [
    'personalTimeTypes' => $personalTimeTypes ?? collect(),
    'personalTimeTypeIcons' => $personalTimeTypeIcons ?? [],
    'section' => 'modals',
  ])
@endsection

@push('scripts')
<script>
(function () {
  var editModal = document.getElementById('personalTimeTypeEditModal');
  if (!editModal) return;
  editModal.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;
    var form = document.getElementById('personalTimeTypeEditForm');
    if (!form) return;
    form.action = btn.getAttribute('data-action') || '#';
    var nameEl = form.querySelector('#pt_edit_name');
    var durEl = form.querySelector('#pt_edit_duration');
    var activeEl = form.querySelector('#pt_edit_is_active');
    if (nameEl) nameEl.value = btn.getAttribute('data-name') || '';
    if (durEl) durEl.value = btn.getAttribute('data-duration') || '60';
    if (activeEl) activeEl.checked = btn.getAttribute('data-active') === '1';

    var iconValue = btn.getAttribute('data-icon') || 'ph-clock';
    form.querySelectorAll('input[name="icon"]').forEach(function (radio) {
      radio.checked = radio.value === iconValue;
    });
  });
})();
</script>
@endpush
