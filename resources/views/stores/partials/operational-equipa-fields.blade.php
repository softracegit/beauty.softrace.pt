{{-- agents, roles, personalTimeLimitStoreHours, storeHoursLabel, showStoreColumn --}}
@php
  $showStoreColumn = (bool) ($showStoreColumn ?? false);
  $idPrefix = $idPrefix ?? '';
  $colspan = $showStoreColumn ? 7 : 6;
@endphp

<div class="uedit-section mb-4">
  <div class="uedit-section-title">Membros</div>
  <p class="text-muted small mb-3">Nível de acesso, visibilidade e ordem na Agenda / Booking.</p>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Membro</th>
          @if ($showStoreColumn)
            <th>Loja</th>
          @endif
          <th>Nível de acesso</th>
          <th class="text-center">Visível na agenda</th>
          <th class="text-center">Visível no Booking</th>
          <th style="max-width: 9rem;">Ordem na agenda</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($agents ?? collect()) as $agent)
          @php
            $fullName = trim((string) $agent->name);
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $firstLast = count($parts) > 1 ? ($parts[0].' '.$parts[count($parts) - 1]) : $fullName;
          @endphp
          <tr>
            <td><div class="fw-semibold">{{ $firstLast }}</div></td>
            @if ($showStoreColumn)
              <td><span class="small text-muted">{{ $agent->store?->name ?? '—' }}</span></td>
            @endif
            <td style="min-width: 15rem;">
              <select class="form-select form-select-sm" name="members[{{ $agent->id }}][role]">
                @foreach(($roles ?? []) as $value => $label)
                  <option value="{{ $value }}" @selected(old("members.{$agent->id}.role", $agent->user?->role) === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </td>
            <td class="text-center">
              <input type="hidden" name="members[{{ $agent->id }}][visible_in_agenda]" value="0">
              <input class="form-check-input" type="checkbox" name="members[{{ $agent->id }}][visible_in_agenda]" value="1"
                @checked((bool) old("members.{$agent->id}.visible_in_agenda", $agent->visible_in_agenda))>
            </td>
            <td class="text-center">
              <input type="hidden" name="members[{{ $agent->id }}][visible_in_booking]" value="0">
              <input class="form-check-input" type="checkbox" name="members[{{ $agent->id }}][visible_in_booking]" value="1"
                @checked((bool) old("members.{$agent->id}.visible_in_booking", $agent->visible_in_booking))>
            </td>
            <td>
              <input type="number" min="1" max="9999" class="form-control form-control-sm"
                name="members[{{ $agent->id }}][agenda_order]"
                value="{{ old("members.{$agent->id}.agenda_order", (int) ($agent->visible_in_agenda ?? false) ? max(1, (int) $agent->agenda_order) : '') }}">
              @error("members.{$agent->id}.agenda_order")
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-light" href="{{ route('equipa.show', $agent) }}" title="Ver ficha"><i class="ph ph-eye"></i></a>
              <a class="btn btn-sm btn-light" href="{{ route('equipa.edit', $agent) }}" title="Editar membro"><i class="ph ph-pencil-simple"></i></a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ $colspan }}" class="text-center text-muted py-4">Sem membros disponíveis.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="uedit-section mb-0">
  <div class="uedit-section-title">Tempo pessoal</div>
  <div class="d-flex align-items-center justify-content-between gap-3">
    <label class="fw-semibold mb-0" for="{{ $idPrefix }}personal_time_limit_store_hours" style="cursor: pointer">
      Limitar o tempo pessoal ao horário da loja
    </label>
    <div class="form-check form-switch m-0 flex-shrink-0">
      <input type="hidden" name="personal_time_limit_store_hours" value="0">
      <input class="form-check-input" type="checkbox" name="personal_time_limit_store_hours" value="1"
        id="{{ $idPrefix }}personal_time_limit_store_hours"
        @checked((bool) old('personal_time_limit_store_hours', $personalTimeLimitStoreHours ?? false))>
    </div>
  </div>
  <p class="small text-muted mb-0 mt-2">
    Quando ligado, só aparecem horas dentro do horário habitual da loja
    (atualmente {{ $storeHoursLabel ?? '09:00–20:00' }}).
  </p>
</div>
