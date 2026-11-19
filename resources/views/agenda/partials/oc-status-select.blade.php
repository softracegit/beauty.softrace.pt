{{-- Select de estado (ícones coloridos). $prefix: agendaOc | eventDetail; $withTerminal: Faltou/Cancelar --}}
@php
    $prefix = $prefix ?? 'eventDetail';
    $withTerminal = !empty($withTerminal);
    $withSourceBadge = !empty($withSourceBadge);
    $statusInputId = $prefix . 'Status';
    $labelForId = $prefix . 'StatusLabelFor';
    $iconId = $prefix . 'StatusIcon';
    $labelId = $prefix . 'StatusLabel';
    $wrapId = $prefix . 'StatusDropdownWrap';
    $btnId = $prefix . 'StatusDropdownBtn';
    $menuId = $prefix . 'StatusMenu';
    $rowId = $prefix . 'StatusRow';
@endphp
<div class="agenda-oc-field event-detail-status-field" id="{{ $rowId }}">
    <label class="form-label fw-semibold mb-1" id="{{ $labelForId }}">Estado</label>
    <input type="hidden" id="{{ $statusInputId }}" name="status" value="agendado">
    <div class="event-detail-status-select-row">
        <div class="dropdown event-detail-status-select" id="{{ $wrapId }}">
            <button
                type="button"
                class="form-select form-select-sm event-detail-status-select__toggle dropdown-toggle"
                id="{{ $btnId }}"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-labelledby="{{ $labelForId }}"
            >
                <span class="event-detail-status-select__current">
                    <span id="{{ $iconId }}" class="event-detail-status-trigger-icon" aria-hidden="true"><i class="ri-time-fill agenda-status-icon-agendado"></i></span>
                    <span id="{{ $labelId }}">Agendado</span>
                </span>
            </button>
            <ul class="dropdown-menu event-detail-status-select__menu" id="{{ $menuId }}" aria-labelledby="{{ $btnId }}">
                <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" data-status="agendado"><i class="ri-time-fill agenda-status-icon-agendado" aria-hidden="true"></i>Agendado</button></li>
                <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" data-status="notificado"><i class="ri-notification-3-fill agenda-status-icon-notificado" aria-hidden="true"></i>Notificado</button></li>
                <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" data-status="confirmado"><i class="ri-notification-3-fill agenda-status-icon-confirmado" aria-hidden="true"></i>Confirmado</button></li>
                <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" data-status="chegou"><i class="ri-map-pin-fill agenda-status-icon-chegou" aria-hidden="true"></i>Chegou</button></li>
                <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" data-status="iniciado"><i class="ri-play-fill agenda-status-icon-iniciado" aria-hidden="true"></i>Iniciado</button></li>
                @if ($withTerminal)
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2 text-danger" data-status="faltou" data-terminal-action="1"><i class="ph ph-x text-danger" aria-hidden="true"></i>Faltou</button></li>
                    <li><button type="button" class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2 text-danger" data-status="cancelar" data-terminal-action="1"><i class="ph ph-trash text-danger" aria-hidden="true"></i>Cancelar</button></li>
                @endif
            </ul>
        </div>
        @if ($withSourceBadge)
            <span class="badge event-detail-marcacao-source-badge d-none" id="eventDetailMarcacaoSourceBadge"></span>
        @endif
    </div>
</div>
