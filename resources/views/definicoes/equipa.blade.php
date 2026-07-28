@extends('definicoes.layout')

@section('definicoes_content')
  <form method="post" action="{{ route('definicoes.equipa.update') }}">
    @csrf

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Equipa</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-4">
          Define o nível de acesso, visibilidade e ordem dos membros na Agenda e no Booking.
        </p>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Membro</th>
                <th>Nível de acesso</th>
                <th class="text-center">Visível na agenda</th>
                <th class="text-center">Visível no Booking</th>
                <th style="max-width: 9rem;">Ordem na agenda</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($agents ?? collect()) as $agent)
                <tr>
                  <td>
                    @php
                      $fullName = trim((string) $agent->name);
                      $parts = preg_split('/\s+/', $fullName) ?: [];
                      $firstLast = count($parts) > 1
                        ? ($parts[0].' '.$parts[count($parts) - 1])
                        : $fullName;
                    @endphp
                    <div class="fw-semibold">{{ $firstLast }}</div>
                  </td>
                  <td style="min-width: 15rem;">
                    <select class="form-select form-select-sm" name="members[{{ $agent->id }}][role]">
                      @foreach(($roles ?? []) as $value => $label)
                        <option value="{{ $value }}" @selected(old("members.{$agent->id}.role", $agent->user?->role) === $value)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td class="text-center">
                    <input type="hidden" name="members[{{ $agent->id }}][visible_in_agenda]" value="0">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="members[{{ $agent->id }}][visible_in_agenda]"
                      value="1"
                      @checked((bool) old("members.{$agent->id}.visible_in_agenda", $agent->visible_in_agenda))
                    >
                  </td>
                  <td class="text-center">
                    <input type="hidden" name="members[{{ $agent->id }}][visible_in_booking]" value="0">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      name="members[{{ $agent->id }}][visible_in_booking]"
                      value="1"
                      @checked((bool) old("members.{$agent->id}.visible_in_booking", $agent->visible_in_booking))
                    >
                  </td>
                  <td>
                    <input
                      type="number"
                      min="1"
                      max="9999"
                      class="form-control form-control-sm"
                      name="members[{{ $agent->id }}][agenda_order]"
                      value="{{ old("members.{$agent->id}.agenda_order", (int) ($agent->visible_in_agenda ?? false) ? max(1, (int) $agent->agenda_order) : '') }}"
                    >
                    @error("members.{$agent->id}.agenda_order")
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('equipa.show', $agent) }}" title="Ver ficha">
                      <i class="ph ph-eye"></i>
                    </a>
                    <a class="btn btn-sm btn-light" href="{{ route('equipa.edit', $agent) }}" title="Editar membro">
                      <i class="ph ph-pencil-simple"></i>
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">Sem membros disponíveis.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">
        <h5 class="card-title mb-0">Tempo pessoal</h5>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-3">
          <label class="fw-semibold mb-0" for="personal_time_limit_store_hours" style="cursor: pointer">
            Limitar o tempo pessoal ao horário da loja
          </label>
          <div class="form-check form-switch m-0 flex-shrink-0">
            <input type="hidden" name="personal_time_limit_store_hours" value="0">
            <input
              class="form-check-input"
              type="checkbox"
              name="personal_time_limit_store_hours"
              value="1"
              id="personal_time_limit_store_hours"
              @checked((bool) old('personal_time_limit_store_hours', $personalTimeLimitStoreHours ?? false))
            >
          </div>
        </div>
        <p class="small text-muted mb-0 mt-2 w-100">
          Quando está ligado, ao criar ou editar tempo pessoal na agenda só aparecem horas dentro do horário habitual da loja
          (atualmente {{ $storeHoursLabel ?? '09:00–20:00' }}, conforme definido em Negócio).
          Quando está desligado, mantém-se a lista completa de horas (00:00–23:45).
        </p>
      </div>
    </div>

    <div class="uedit-form-actions pt-3">
      <button type="submit" class="btn btn-primary">
        <i class="ph ph-check me-1"></i> Guardar alterações
      </button>
    </div>
  </form>

  @if (session('status'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.showToast === 'function') {
          window.showToast(@json(session('status')), 'success');
        }
      });
    </script>
  @endif
@endsection
