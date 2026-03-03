@extends('partials.layouts.main')
@section('title', 'Agenda | Beauty CRM')
@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.css">
    <link rel="stylesheet" href="{{ asset('template/css/agenda.css') }}">
@endsection
@section('content')
<div class="row g-0">
    <div class="col-12 p-0">
        <div class="card border-0 shadow-none">
            <div class="card-body p-0">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick menu popup (ao clicar numa célula) - mesmo aspeto do quick access da navbar -->
<div id="agendaQuickMenu" role="menu" aria-label="Opções"></div>

<!-- Modal: Nova marcação (inspirado em apps-support ticket detail) -->
<div class="modal fade" id="novaMarcacaoModal" tabindex="-1" aria-labelledby="novaMarcacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header pb-3 d-flex align-items-center justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold d-flex flex-wrap align-items-center gap-1">
                    <span id="novaMarcacaoEditTitleDay">—</span>
                    <span class="dropdown">
                        <span class="event-detail-time-toggle dropdown-toggle" id="novaMarcacaoTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">—</span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="novaMarcacaoTimeDropdownMenu">
                            <div class="px-3 py-2 border-bottom"><label class="form-label small mb-0">Alterar hora de início</label></div>
                            <div class="nova-marcacao-time-options agenda-time-options-scroll"></div>
                        </div>
                    </span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="novaMarcacaoForm">
                <input type="hidden" id="novaMarcacaoAgentId" name="user_id">
                <input type="hidden" id="novaMarcacaoStart" name="start_at">
                <input type="hidden" id="novaMarcacaoEnd" name="end_at">
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="nova-marcacao-sidebar">
                                <div class="nova-marcacao-section">
                                    <h6 class="nova-marcacao-section-title">Prestador do Serviço</h6>
                                    <a id="novaMarcacaoAgentLink" href="#" class="nova-marcacao-person nova-marcacao-agent-link text-decoration-none text-body">
                                        <img id="novaMarcacaoAgentAvatar" src="" alt="" class="rounded-circle agenda-avatar-img" width="40" height="40">
                                        <div class="flex-grow-1 min-w-0">
                                            <strong id="novaMarcacaoAgentName">—</strong>
                                            <span id="novaMarcacaoAgentEmail" class="d-block small text-muted">—</span>
                                        </div>
                                    </a>
                                </div>
                                <div class="nova-marcacao-section">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="nova-marcacao-section-title mb-0">Cliente</h6>
                                        <a href="#" class="nova-marcacao-create-client-link" id="novaMarcacaoCreateClientBtn"><i class="ph ph-plus"></i> Novo cliente</a>
                                    </div>
                                    <div id="novaMarcacaoClientSearchWrap" class="mb-2">
                                        <div class="d-flex gap-2 align-items-center mb-2">
                                            <input type="text" id="novaMarcacaoClientSearch" class="form-control form-control-sm flex-grow-1" placeholder="Pesquisar cliente..." autocomplete="off">
                                            <button type="button" class="btn btn-light btn-sm d-none" id="novaMarcacaoClientCancelBtn">Cancelar</button>
                                        </div>
                                    </div>
                                    <div id="novaMarcacaoClientResults" class="nova-marcacao-client-results mb-0">
                                    </div>
                                    <div id="novaMarcacaoClientSelected" class="nova-marcacao-person d-none">
                                        <img id="novaMarcacaoClientAvatar" src="" alt="" class="rounded-circle agenda-avatar-img d-none" width="40" height="40">
                                        <div id="novaMarcacaoClientAvatarFallback" class="nova-marcacao-avatar-fallback agenda-avatar-fallback rounded-circle d-flex align-items-center justify-content-center small fw-semibold d-none">—</div>
                                        <div class="flex-grow-1 min-w-0">
                                            <strong id="novaMarcacaoClientSelectedName">—</strong>
                                            <span id="novaMarcacaoClientSelectedEmail" class="d-block small text-muted">—</span>
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm p-0 align-self-start" id="novaMarcacaoClientClear">Alterar</button>
                                    </div>
                                </div>
                                <div class="nova-marcacao-section">
                                    <button type="button" class="nova-marcacao-observacoes-toggle nova-marcacao-section-title border-0 bg-transparent p-0 text-start d-flex align-items-center justify-content-between w-100" id="novaMarcacaoObservacoesToggle">
                                        Observações
                                        <i class="ph ph-caret-down nova-marcacao-observacoes-chevron agenda-observacoes-chevron"></i>
                                    </button>
                                    <div class="nova-marcacao-observacoes-wrap collapse" id="novaMarcacaoObservacoesWrap">
                                        <textarea class="form-control form-control-sm mt-2" id="novaMarcacaoObservacoes" name="description" rows="2" placeholder="Observações..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <h6 class="mb-3 fw-semibold" id="novaMarcacaoServicesTitle">Serviços</h6>
                            <div id="novaMarcacaoServicesListCancelWrap" class="d-none mb-3">
                                <button type="button" class="btn btn-light btn-sm" id="novaMarcacaoCancelAddServicesBtn"><i class="ph ph-arrow-left me-1"></i>Voltar</button>
                            </div>
                            <div id="novaMarcacaoServicesList" class="nova-marcacao-services-list">
                                <div class="text-muted small">A carregar serviços...</div>
                            </div>
                            <div id="novaMarcacaoServiceSelected" class="d-none">
                                <div class="nova-marcacao-services-selected-title">Serviços selecionados</div>
                                <div id="novaMarcacaoSelectedServicesList"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="novaMarcacaoAddMoreServicesBtn">
                                    <i class="ph ph-plus me-1"></i>Adicionar mais serviços
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer pt-3 pb-3 d-flex justify-content-between align-items-center">
                    <div class="me-auto">
                        <span class="text-black fs-6 fw-bold">Total:</span>
                        <span class="fw-semibold ms-1" id="novaMarcacaoTotalPrice">0,00 €</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary ms-2" id="novaMarcacaoSubmitBtn">Criar marcação</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick menu: Editar opções do serviço (duração, preço) -->
<div id="novaMarcacaoEditServiceQuickMenu" role="dialog" aria-label="Alterar opções do serviço"></div>
<!-- Quick menu: Adicionar extra ao serviço -->
<div id="novaMarcacaoAddExtrasQuickMenu" role="dialog" aria-label="Adicionar extra" class="nova-marcacao-quick-menu-extras"></div>
<!-- Event detail: quick menu Adicionar extra (reutiliza estilos; posicionado no modal) -->
<div id="eventDetailAddExtrasQuickMenu" role="dialog" aria-label="Adicionar extra" class="nova-marcacao-quick-menu-extras" style="position: absolute;"></div>

<!-- Quick menu: Criar cliente -->
<div id="agendaCreateClientQuickMenu" role="dialog" aria-label="Criar cliente"></div>

<!-- Modal: Criar / Editar evento (apenas manual/outro) -->
<div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pb-2 d-flex align-items-start justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold" id="createEventModalLabel">Novo evento</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="createEventForm">
                <div class="modal-body pt-5">
                    <input type="hidden" id="eventId" name="event_id">
                    <div class="mb-3">
                        <label for="eventTitle" class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="eventTitle" name="title" required maxlength="255" placeholder="Título do evento">
                    </div>
                    <div class="mb-3">
                        <label for="eventType" class="form-label">Tipo</label>
                        <select class="form-select" id="eventType" name="event_type">
                            <option value="marcacao">Marcação</option>
                            <option value="tempo_pessoal">Tempo pessoal</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="eventUser" class="form-label">Membro</label>
                        <select class="form-select" id="eventUser" name="user_id">
                            @if(auth()->user()->role === \App\Models\User::ROLE_ADMIN)
                                <option value="">— Selecionar membro —</option>
                            @else
                                <option value="">Eu ({{ auth()->user()->name }})</option>
                            @endif
                            @foreach($users as $u)
                                @if($u->id !== auth()->id() && $u->role !== \App\Models\User::ROLE_ADMIN)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="eventServiceWrap">
                        <label for="eventService" class="form-label">Serviço <span class="text-danger">*</span></label>
                        <select class="form-select" id="eventService" name="service_id">
                            <option value="">Selecione o membro primeiro</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="eventStart" class="form-label">Início <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="eventStart" name="start_at" required>
                        </div>
                        <div class="col-md-6">
                            <label for="eventEnd" class="form-label">Fim <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="eventEnd" name="end_at" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="eventDescription" class="form-label">Descrição / Observações</label>
                        <textarea class="form-control" id="eventDescription" name="description" rows="3" placeholder="Descrição ou observações..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="createEventSubmitBtn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Ver/Editar Evento (layout Nova Marcação, unificado) -->
<div class="modal fade" id="eventDetailEditModal" tabindex="-1" aria-labelledby="eventDetailEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header pb-3 d-flex align-items-center justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold d-flex flex-wrap align-items-center gap-1">
                    <span id="eventDetailEditTitleDay">—</span>
                    <span class="dropdown">
                        <span class="event-detail-time-toggle dropdown-toggle" id="eventDetailTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">—</span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="eventDetailTimeDropdownMenu">
                            <div class="px-3 py-2 border-bottom"><label class="form-label small mb-0">Alterar hora de início</label></div>
                            <div class="event-detail-time-options agenda-time-options-scroll">
                                <!-- Opções 00:00 - 23:30 geradas em JS -->
                            </div>
                        </div>
                    </span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="eventDetailEditForm">
                <input type="hidden" id="eventDetailEditId" name="event_id">
                <input type="hidden" id="eventDetailEditUserId" name="user_id">
                <input type="hidden" id="eventDetailEditStart" name="start_at">
                <input type="hidden" id="eventDetailEditEnd" name="end_at">
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="nova-marcacao-sidebar">
                                <div class="nova-marcacao-section d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <div class="dropdown" id="eventDetailStatusDropdownWrap">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" id="eventDetailStatusDropdownBtn">
                                            <span id="eventDetailStatusIcon"><i class="me-2 ph ph-clock"></i></span>
                                            <span id="eventDetailStatusLabel">Agendado</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-start p-0" id="eventDetailStatusMenu">
                                            <div class="px-3 py-2 border-bottom"><label class="form-label small mb-0">Estado</label></div>
                                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="agendado"><i class="me-0 ph ph-clock"></i>Agendado</a>
                                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="confirmado"><i class="me-0 ph ph-check"></i>Confirmado</a>
                                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="chegou"><i class="me-0 ph ph-map-pin"></i>Chegou</a>
                                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="iniciado"><i class="me-0 ph ph-play"></i>Iniciado</a>
                                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="faltou"><i class="me-0 ph ph-prohibit"></i>Faltou</a>
                                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="cancelado"><i class="me-0 ph ph-x-circle"></i>Cancelado</a>
                                        </div>
                                    </div>
                                </div>
                                <div id="eventDetailCancelReasonWrap" class="d-none mb-3">
                                    <label for="eventDetailCancelReason" class="form-label small">Razão do cancelamento</label>
                                    <textarea class="form-control form-control-sm" id="eventDetailCancelReason" name="cancellation_reason" rows="2" placeholder="Indique o motivo do cancelamento..."></textarea>
                                </div>
                                <div class="nova-marcacao-section">
                                    <h6 class="nova-marcacao-section-title">Prestador do Serviço</h6>
                                    <a id="eventDetailAgentLink" href="#" class="nova-marcacao-person nova-marcacao-agent-link text-decoration-none text-body">
                                        <img id="eventDetailAgentAvatar" src="" alt="" class="rounded-circle agenda-avatar-img" width="40" height="40">
                                        <div class="flex-grow-1 min-w-0">
                                            <strong id="eventDetailAgentName">—</strong>
                                            <span id="eventDetailAgentEmail" class="d-block small text-muted">—</span>
                                        </div>
                                    </a>
                                </div>
                                <div class="nova-marcacao-section" id="eventDetailClientSection">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="nova-marcacao-section-title mb-0">Cliente</h6>
                                        <a href="#" class="nova-marcacao-create-client-link d-none" id="eventDetailCreateClientBtn"><i class="ph ph-plus"></i> Novo cliente</a>
                                    </div>
                                    <div id="eventDetailClientSearchWrap" class="mb-0">
                                        <div class="d-flex gap-2 align-items-center mb-2">
                                            <input type="text" id="eventDetailClientSearch" class="form-control form-control-sm flex-grow-1" placeholder="Pesquisar cliente..." autocomplete="off">
                                            <button type="button" class="btn btn-light btn-sm d-none" id="eventDetailClientCancelBtn">Cancelar</button>
                                        </div>
                                    </div>
                                    <div id="eventDetailClientResults" class="nova-marcacao-client-results mb-0">
                                    </div>
                                    <div id="eventDetailClientSelected" class="nova-marcacao-person d-none">
                                        <img id="eventDetailClientAvatar" src="" alt="" class="rounded-circle agenda-avatar-img d-none" width="40" height="40">
                                        <div id="eventDetailClientAvatarFallback" class="nova-marcacao-avatar-fallback agenda-avatar-fallback rounded-circle d-flex align-items-center justify-content-center small fw-semibold d-none">—</div>
                                        <div class="flex-grow-1 min-w-0">
                                            <strong id="eventDetailClientSelectedName">—</strong>
                                            <span id="eventDetailClientSelectedEmail" class="d-block small text-muted">—</span>
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm p-0 align-self-start" id="eventDetailClientClear">Alterar</button>
                                    </div>
                                    <div id="eventDetailVisitLeadBlock" class="d-none"></div>
                                </div>
                                <input type="hidden" id="eventDetailStatus" name="status" value="agendado">
                                <div class="nova-marcacao-section">
                                    <button type="button" class="nova-marcacao-observacoes-toggle nova-marcacao-section-title border-0 bg-transparent p-0 text-start d-flex align-items-center justify-content-between w-100" id="eventDetailObservacoesToggle">
                                        Observações
                                        <i class="ph ph-caret-down nova-marcacao-observacoes-chevron agenda-observacoes-chevron"></i>
                                    </button>
                                    <div class="nova-marcacao-observacoes-wrap collapse" id="eventDetailObservacoesWrap">
                                        <textarea class="form-control form-control-sm mt-2" id="eventDetailObservacoes" name="description" rows="2" placeholder="Observações..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7" id="eventDetailServicesCol">
                            <h6 class="mb-3 fw-semibold">Serviços</h6>
                            <div id="eventDetailServicesListCancelWrap" class="d-none mb-2">
                                <button type="button" class="btn btn-light btn-sm" id="eventDetailCancelAddServicesBtn"><i class="ph ph-arrow-left me-1"></i>Cancelar</button>
                            </div>
                            <div id="eventDetailServicesList" class="nova-marcacao-services-list">
                                <div class="text-muted small">A carregar serviços...</div>
                            </div>
                            <div id="eventDetailServiceSelected" class="d-none">
                                <div class="nova-marcacao-services-selected-title">Serviços selecionados</div>
                                <div id="eventDetailSelectedServicesList"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="eventDetailAddMoreServicesBtn">
                                    <i class="ph ph-plus me-1"></i>Adicionar mais serviços
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer pt-3 pb-3 d-flex justify-content-between align-items-center">
                    <div class="me-auto">
                        <span class="text-black fs-6 fw-bold">Total:</span>
                        <span class="fw-semibold ms-1" id="eventDetailTotalPrice">0,00 €</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary ms-2" id="eventDetailSaveBtn">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/pt.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const eventsUrl = '{{ route('agenda.events') }}';
    const resourcesUrl = '{{ route('agenda.resources') }}';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const currentUserIsAdmin = {{ json_encode(auth()->user()->role === \App\Models\User::ROLE_ADMIN) }};

    let allResources = [];
    let consultantFilterIds = [];
    let currentViewMode = 'normal';
    let selectedConsultantId = '';
    let eventDetailModalLoading = false;

    // Formatar data para prev/next: "Qui 5 Fev"
    function formatDateShort(date) {
        const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        const d = new Date(date);
        return days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()];
    }
    
    // Formatar data do botão currentDate conforme a vista
    function formatCurrentDateButton(viewType, startDate, endDate) {
        const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        const monthsShort = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        const monthsLong = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        
        const start = new Date(startDate);
        const end = endDate ? new Date(endDate) : null;
        
        // Vista de mês: "Fevereiro 2026"
        if (viewType === 'dayGridMonth') {
            return monthsLong[start.getMonth()] + ' ' + start.getFullYear();
        }
        
        // Vista por consultor: "Qui 5 Fev" (nome do dia + dia + mês)
        if (viewType === 'resourceTimeGridDay') {
            return days[start.getDay()] + ' ' + start.getDate() + ' ' + monthsShort[start.getMonth()];
        }
        
        // Vista de semana: "2 Fev - 8 Fev, 2026"
        if (viewType === 'timeGridWeek') {
            if (end && start.getTime() !== end.getTime()) {
                const startDay = start.getDate();
                const startMonth = monthsShort[start.getMonth()];
                const endDay = end.getDate();
                const endMonth = monthsShort[end.getMonth()];
                const year = start.getFullYear();
                
                // Se for o mesmo mês
                if (start.getMonth() === end.getMonth()) {
                    return startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth + ', ' + year;
                } else {
                    return startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth + ', ' + year;
                }
            } else {
                // Vista de dia único
                return start.getDate() + ' ' + monthsShort[start.getMonth()] + ', ' + start.getFullYear();
            }
        }
        
        // Fallback: usar formato curto
        return formatDateShort(start);
    }

    var _agendaHighlight = { wrapper: null };
    var _agendaHoverHighlight = null;

    function clearAgendaHoverHighlight() {
        if (_agendaHoverHighlight) {
            _agendaHoverHighlight.remove();
            _agendaHoverHighlight = null;
        }
    }

    function clearAgendaCellHighlight() {
        var w = _agendaHighlight.wrapper;
        if (!w) return;
        if (w._isDayGrid && w._parent) {
            w._parent.classList.remove('agenda-cell-highlighted');
        } else if (w._isFullRow) {
            w.remove();
        } else if (w.remove) {
            w.remove();
        }
        _agendaHighlight.wrapper = null;
    }

    function createCellHighlightForColumn(slotTd, resourceId, timeLabel, clickClientX) {
        if (!slotTd) return null;
        var colEl = document.querySelector('.fc-timegrid-col[data-resource-id="' + resourceId + '"]') ||
            document.querySelector('[data-resource-id="' + resourceId + '"]');
        if (!colEl && clickClientX != null) {
            var cols = document.querySelectorAll('.fc-timegrid-col');
            for (var i = 0; i < cols.length; i++) {
                var r = cols[i].getBoundingClientRect();
                if (clickClientX >= r.left && clickClientX <= r.right) { colEl = cols[i]; break; }
            }
        }
        if (!colEl) return null;
        var slotRect = slotTd.getBoundingClientRect();
        var colRect = colEl.getBoundingClientRect();
        if (colRect.width <= 0 || slotRect.height <= 0) return null;
        var wrapper = document.createElement('div');
        wrapper.className = 'agenda-cell-highlight agenda-cell-highlight-active';
        wrapper.style.position = 'fixed';
        wrapper.style.top = slotRect.top + 'px';
        wrapper.style.left = colRect.left + 'px';
        wrapper.style.width = colRect.width + 'px';
        wrapper.style.height = slotRect.height + 'px';
        wrapper.style.zIndex = '9998';
        wrapper.style.pointerEvents = 'none';
        var timeSpan = document.createElement('span');
        timeSpan.className = 'agenda-cell-time-overlay';
        timeSpan.textContent = timeLabel;
        wrapper.appendChild(timeSpan);
        document.body.appendChild(wrapper);
        return wrapper;
    }

    /**
     * Mostra o menu rápido (popup) na posição do rato com as opções dadas.
     * @param {number} clientX - posição X do rato (ex: info.jsEvent.clientX)
     * @param {number} clientY - posição Y do rato (ex: info.jsEvent.clientY)
     * @param {string} [headingText] - texto do primeiro li (data/hora); bold, fundo cinza; opcional
     * @param {Array<{label: string, action: function}>} options - lista de { label, action }
     */
    function showQuickMenu(clientX, clientY, headingText, options) {
        if (typeof headingText === 'object' && !Array.isArray(headingText) && headingText !== null) {
            options = headingText;
            headingText = null;
        }
        var menu = document.getElementById('agendaQuickMenu');
        if (!menu || !options || options.length === 0) return;

        function hideQuickMenu() {
            menu.classList.remove('is-open');
            document.removeEventListener('click', closeHandler);
            window.removeEventListener('scroll', scrollHandler, true);
            document.removeEventListener('keydown', escHandler);
            clearAgendaCellHighlight();
        }

        function escHandler(e) {
            if (e.key === 'Escape') hideQuickMenu();
        }

        function scrollHandler() {
            hideQuickMenu();
        }

        function closeHandler(e) {
            if (menu.contains(e.target)) return;
            hideQuickMenu();
        }

        menu.innerHTML = '';
        var header = document.createElement('div');
        header.className = 'quickaccess-header';
        var h6 = document.createElement('h6');
        h6.textContent = headingText || '';
        header.appendChild(h6);
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'quickaccess-close';
        closeBtn.setAttribute('aria-label', 'Fechar');
        closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideQuickMenu();
        });
        header.appendChild(closeBtn);
        menu.appendChild(header);
        var grid = document.createElement('div');
        grid.className = 'quickaccess-grid';
        options.forEach(function(opt) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quickaccess-item';
            btn.setAttribute('role', 'menuitem');
            var iconSpan = document.createElement('span');
            iconSpan.className = 'quickaccess-icon';
            var qaColor = opt.iconColor || 'var(--accent-color, #0d6efd)';
            iconSpan.style.setProperty('--qa-color', qaColor);
            var iconClass = opt.icon || 'bi bi-plus-circle';
            var icon = document.createElement('i');
            icon.className = iconClass;
            iconSpan.appendChild(icon);
            var labelSpan = document.createElement('span');
            labelSpan.className = 'quickaccess-label';
            labelSpan.textContent = opt.label;
            btn.appendChild(iconSpan);
            btn.appendChild(labelSpan);
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideQuickMenu();
                if (typeof opt.action === 'function') opt.action();
            });
            grid.appendChild(btn);
        });
        menu.appendChild(grid);

        var offset = 8;
        menu.style.left = (clientX + offset) + 'px';
        menu.style.top = (clientY + offset) + 'px';

        // Manter dentro da viewport
        requestAnimationFrame(function() {
            var rect = menu.getBoundingClientRect();
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var left = parseFloat(menu.style.left);
            var top = parseFloat(menu.style.top);
            if (left + rect.width > vw) menu.style.left = (vw - rect.width - 8) + 'px';
            if (top + rect.height > vh) menu.style.top = (vh - rect.height - 8) + 'px';
            if (top < 8) menu.style.top = '8px';
            if (left < 8) menu.style.left = '8px';
        });

        menu.classList.add('is-open');
        setTimeout(function() {
            document.addEventListener('click', closeHandler);
            window.addEventListener('scroll', scrollHandler, true);
            document.addEventListener('keydown', escHandler);
        }, 0);
    }

    /**
     * Abre o modal de criar evento (reutilizado pelo botão e pelo clique na célula).
     * Opcionalmente preenche início, fim, membro e tipo (marcacao | tempo_pessoal).
     */
    function openCreateEventModal(initialStart, initialEnd, initialMemberId, initialEventType) {
        if (initialStart) document.getElementById('eventStart').value = initialStart;
        if (initialEnd) document.getElementById('eventEnd').value = initialEnd;
        if (initialMemberId) {
            document.getElementById('eventUser').value = String(initialMemberId);
        }
        if (initialEventType) {
            document.getElementById('eventType').value = initialEventType;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('createEventModal')).show();
        toggleEventServiceBlock();
    }

    var agendaMembersServicesUrl = '{{ url("agenda/members") }}';
    var agendaClientsUrl = '{{ url("agenda/clients") }}';
    var agendaEquipaBaseUrl = '{{ url("equipa") }}';
    var novaMarcacaoServicesData = null;
    var novaMarcacaoSelectedClient = null;
    var novaMarcacaoSelectedServices = [];
    var novaMarcacaoEditServiceIndex = -1;
    var eventDetailSelectedServices = [];
    var eventDetailCurrentData = null;

    function novaMarcacaoRenderSelectedServices() {
        var container = document.getElementById('novaMarcacaoSelectedServicesList');
        var titleEl = document.querySelector('#novaMarcacaoServiceSelected .nova-marcacao-services-selected-title');
        if (!container) return;
        if (novaMarcacaoSelectedServices.length === 0) {
            container.innerHTML = '';
            if (titleEl) titleEl.textContent = 'Serviços selecionados';
            return;
        }
        if (titleEl) titleEl.textContent = novaMarcacaoSelectedServices.length === 1 ? 'Serviço selecionado' : 'Serviços selecionados';
        var html = novaMarcacaoSelectedServices.map(function(item, idx) {
            var extrasLine = (item.extras && item.extras.length)
                ? '<div class="small text-muted mt-1 nova-marcacao-extras-line">' +
                    item.extras.map(function(e, eIdx) {
                        var priceText = e.formatted_price || ((parseFloat(e.price) || 0).toFixed(2).replace('.', ',') + ' €');
                        var durText = e.formatted_duration || ((e.duration || 0) + ' min');
                        return '<span class="badge rounded-pill text-bg-light me-1">' +
                            '+ ' + (e.name || '') + ' (' + priceText + ' · ' + durText + ')' +
                            '<button type="button" class="btn btn-link btn-sm p-0 ms-1 novaMarcacaoRemoveExtraBtn" data-idx="' + idx + '" data-extra-index="' + eIdx + '" aria-label="Remover extra"><i class="ph ph-x"></i></button>' +
                            '</span>';
                    }).join('') +
                  '</div>'
                : '';
            var hasAvailableExtras = (item.available_extras && item.available_extras.length > 0);
            var addExtrasBtn = hasAvailableExtras ? '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm novaMarcacaoAddExtrasBtn" data-idx="' + idx + '" title="Adicionar extras" aria-label="Adicionar extras"><i class="ph ph-plus-circle"></i></button>' : '';
            var origP = item.original_price != null ? parseFloat(item.original_price) : NaN;
            var currP = item.price != null ? parseFloat(item.price) : NaN;
            var showStrikethrough = !isNaN(origP) && currP !== origP;
            var priceBlock = showStrikethrough
                ? '<span class="text-danger text-decoration-line-through small me-1">' + (origP.toFixed(2).replace('.', ',') + ' €') + '</span><span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>'
                : '<span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>';
            return '<div class="nova-marcacao-service-item nova-marcacao-service-selected-card d-flex justify-content-between align-items-center mb-2" data-idx="' + idx + '" style="border-left-color:' + (item.color || '#6c757d') + '">' +
                '<div class="nova-marcacao-service-item-left">' +
                '<div class="nova-marcacao-service-item-name">' + (item.name || '') + '</div>' +
                '<div class="nova-marcacao-service-item-duration"><i class="ph ph-clock me-1"></i>' + (item.formatted_duration || item.duration + ' min') + '</div>' + extrasLine +
                '</div>' +
                '<div class="d-flex align-items-center gap-2 justify-content-end">' +
                '<div class="d-flex gap-1">' + addExtrasBtn +
                '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm novaMarcacaoEditServiceBtn" data-idx="' + idx + '" title="Alterar opções" aria-label="Alterar opções"><i class="ph ph-pencil-simple"></i></button>' +
                '<button type="button" class="btn btn-outline-danger btn-icon btn-sm novaMarcacaoDeleteServiceBtn" data-idx="' + idx + '" title="Eliminar" aria-label="Eliminar"><i class="ph ph-trash"></i></button>' +
                '</div>' +
                priceBlock +
                '</div></div></div>';
        }).join('');
        container.innerHTML = html;
        container.querySelectorAll('.novaMarcacaoEditServiceBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); novaMarcacaoOpenEditQuickMenu(e, parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.novaMarcacaoDeleteServiceBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); novaMarcacaoDeleteService(parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.novaMarcacaoAddExtrasBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); novaMarcacaoOpenAddExtrasQuickMenu(e, parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.novaMarcacaoRemoveExtraBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var sIdx = parseInt(this.dataset.idx, 10);
                var exIdx = parseInt(this.dataset.extraIndex, 10);
                if (!isNaN(sIdx) && !isNaN(exIdx) && novaMarcacaoSelectedServices[sIdx] && Array.isArray(novaMarcacaoSelectedServices[sIdx].extras)) {
                    novaMarcacaoSelectedServices[sIdx].extras.splice(exIdx, 1);
                    novaMarcacaoRenderSelectedServices();
                    novaMarcacaoUpdateEndTimeAndTotal();
                }
            });
        });
    }

    function novaMarcacaoOpenAddExtrasQuickMenu(evt, idx) {
        var item = novaMarcacaoSelectedServices[idx];
        if (!item || !item.available_extras || !item.available_extras.length) return;
        var addedIds = (item.extras || []).map(function(e) { return e.id; });
        var toShow = item.available_extras.filter(function(e) { return addedIds.indexOf(e.id) === -1; });
        if (!toShow.length) {
            showToast('Não há mais extras disponíveis para este serviço.', 'info');
            return;
        }
        var popup = document.getElementById('novaMarcacaoAddExtrasQuickMenu');
        if (!popup) return;
        var modalContent = document.getElementById('novaMarcacaoModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        function hide() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', ch);
            document.removeEventListener('keydown', eh);
        }
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        popup.innerHTML = '<div class="edit-service-header"><h6>Adicionar extra</h6><button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div><div class="edit-service-body"><div class="list-group list-group-flush" id="novaMarcacaoAddExtrasList"></div></div>';
        popup.querySelector('.edit-service-close').addEventListener('click', hide);
        var list = document.getElementById('novaMarcacaoAddExtrasList');
        toShow.forEach(function(ex) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            a.innerHTML = '<span>' + (ex.name || '').replace(/</g, '&lt;') + '</span><span class="small text-muted">' + (ex.formatted_price || ex.price + ' €') + ' · ' + (ex.formatted_duration || ex.duration + ' min') + '</span>';
            a.addEventListener('click', function(e) {
                e.preventDefault();
                if (!novaMarcacaoSelectedServices[idx].extras) novaMarcacaoSelectedServices[idx].extras = [];
                novaMarcacaoSelectedServices[idx].extras.push({ id: ex.id, name: ex.name, duration: ex.duration || 0, price: ex.price || 0, formatted_duration: ex.formatted_duration || (ex.duration || 0) + ' min', formatted_price: ex.formatted_price || (ex.price || 0).toFixed(2).replace('.', ',') + ' €' });
                hide();
                novaMarcacaoRenderSelectedServices();
                novaMarcacaoUpdateEndTimeAndTotal();
            });
            list.appendChild(a);
        });
        popup.classList.add('is-open');
        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
        var left = container ? (rect.left - container.left) : (rect.left || 0);
        var top = container ? (rect.bottom + offset - container.top) : ((rect.bottom || 0) + offset);
        popup.style.left = (left || 0) + 'px';
        popup.style.top = (top || 0) + 'px';
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    function novaMarcacaoApplyNewStartTime(newTimeStr) {
        var startStr = document.getElementById('novaMarcacaoStart').value;
        if (!startStr) return;
        var start = new Date(startStr);
        var parts = (newTimeStr || '').match(/^(\d{1,2}):(\d{2})/);
        if (!parts) return;
        start.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
        var totalDur = novaMarcacaoSelectedServices.reduce(function(sum, s) { return sum + (s.duration || 0); }, 0);
        if (totalDur < 1) totalDur = 60;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var startIso = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0') + 'T' + String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        var endIso = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        document.getElementById('novaMarcacaoStart').value = startIso;
        document.getElementById('novaMarcacaoEnd').value = endIso;
        var daysPt = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var monthsPtShort = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        document.getElementById('novaMarcacaoEditTitleDay').textContent = daysPt[start.getDay()] + ', ' + start.getDate() + ' ' + monthsPtShort[start.getMonth()] + ' · ';
        document.getElementById('novaMarcacaoTimeToggle').textContent = String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        novaMarcacaoUpdateEndTimeAndTotal();
        var dd = document.getElementById('novaMarcacaoTimeToggle');
        if (dd && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(dd);
            if (inst) inst.hide();
        }
    }

    function novaMarcacaoPopulateTimeOptions(selectedTime) {
        var container = document.querySelector('.nova-marcacao-time-options');
        if (!container) return;
        container.innerHTML = '';
        for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += 15) {
                var ts = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item nova-marcacao-time-opt' + (ts === selectedTime ? ' active' : '');
                a.dataset.time = ts;
                a.textContent = ts;
                a.addEventListener('click', function(e) { e.preventDefault(); novaMarcacaoApplyNewStartTime(this.dataset.time); });
                container.appendChild(a);
            }
        }
    }

    function novaMarcacaoUpdateEndTimeAndTotal() {
        var startStr = document.getElementById('novaMarcacaoStart').value;
        if (!startStr) return;
        var totalDur = novaMarcacaoSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        var start = new Date(startStr);
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        document.getElementById('novaMarcacaoEnd').value = endStr;
        var totalPrice = novaMarcacaoSelectedServices.reduce(function(sum, s) {
            var p = (parseFloat(s.price) || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (parseFloat(e.price) || 0); }, 0);
            return sum + p;
        }, 0);
        document.getElementById('novaMarcacaoTotalPrice').textContent = totalPrice.toFixed(2).replace('.', ',') + ' €';
    }

    function novaMarcacaoOpenEditQuickMenu(evt, idx) {
        novaMarcacaoEditServiceIndex = idx;
        var item = novaMarcacaoSelectedServices[idx];
        if (!item) return;
        var popup = document.getElementById('novaMarcacaoEditServiceQuickMenu');
        if (!popup) return;
        var modalContent = document.getElementById('novaMarcacaoModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);

        function hideEditQuickMenu() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', closeHandler);
            document.removeEventListener('keydown', escHandler);
            window._hideEditServiceQuickMenu = null;
        }
        window._hideEditServiceQuickMenu = hideEditQuickMenu;
        function closeHandler(e) {
            if (popup.contains(e.target)) return;
            hideEditQuickMenu();
        }
        function escHandler(e) {
            if (e.key === 'Escape') { e.stopPropagation(); hideEditQuickMenu(); }
        }

        popup.innerHTML = '<div class="edit-service-header">' +
            '<h6>Alterar opções do serviço</h6>' +
            '<button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>' +
            '</div>' +
            '<div class="edit-service-body">' +
            '<div class="mb-2"><label class="form-label small">Duração (minutos)</label>' +
            '<input type="number" class="form-control form-control-sm novaMarcacaoEditDuration" min="1" step="1" placeholder="Ex: 60" value="' + (item.duration || '') + '"></div>' +
            '<div class="mb-0"><label class="form-label small">Preço (€)</label>' +
            '<input type="number" class="form-control form-control-sm novaMarcacaoEditPrice" min="0" step="0.01" placeholder="Ex: 25" value="' + (item.price != null && item.price !== '' ? item.price : '') + '"></div>' +
            '</div>' +
            '<div class="edit-service-footer">' +
            '<button type="button" class="btn btn-light btn-sm novaMarcacaoEditCancel">Cancelar</button>' +
            '<button type="button" class="btn btn-primary btn-sm novaMarcacaoEditSave">Guardar</button>' +
            '</div>';

        var header = popup.querySelector('.edit-service-header');
        header.querySelector('.edit-service-close').addEventListener('click', hideEditQuickMenu);

        popup.querySelector('.novaMarcacaoEditCancel').addEventListener('click', hideEditQuickMenu);

        popup.querySelector('.novaMarcacaoEditSave').addEventListener('click', function() {
            var durInput = popup.querySelector('.novaMarcacaoEditDuration');
            var priceInput = popup.querySelector('.novaMarcacaoEditPrice');
            var dur = parseInt(durInput.value, 10);
            var price = parseFloat(priceInput.value);
            var i = novaMarcacaoEditServiceIndex;
            if (i >= 0 && novaMarcacaoSelectedServices[i]) {
                if (!isNaN(dur) && dur > 0) novaMarcacaoSelectedServices[i].duration = dur;
                if (!isNaN(price) && price >= 0) {
                    novaMarcacaoSelectedServices[i].price = price;
                    novaMarcacaoSelectedServices[i].formatted_price = price.toFixed(2).replace('.', ',') + ' €';
                }
                novaMarcacaoSelectedServices[i].formatted_duration = (novaMarcacaoSelectedServices[i].duration || 0) + ' min';
                novaMarcacaoRenderSelectedServices();
                novaMarcacaoUpdateEndTimeAndTotal();
            }
            hideEditQuickMenu();
        });

        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        requestAnimationFrame(function() {
            popup.classList.add('is-open');
            var popupRect = popup.getBoundingClientRect();
            var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
            var left, top;
            if (container) {
                left = (rect.left || evt.clientX) - container.left;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset - container.top;
            } else {
                left = rect.left || evt.clientX;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset;
            }
            var vw = container ? container.width : window.innerWidth;
            var vh = container ? container.height : window.innerHeight;
            var maxLeft = vw - popupRect.width - offset;
            var maxTop = vh - popupRect.height - offset;
            left = Math.max(offset, Math.min(left, maxLeft));
            top = Math.max(offset, Math.min(top, maxTop));
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
        });
        setTimeout(function() {
            document.addEventListener('click', closeHandler);
            document.addEventListener('keydown', escHandler);
        }, 0);
    }

    function novaMarcacaoDeleteService(idx) {
        if (typeof window._hideEditServiceQuickMenu === 'function') { window._hideEditServiceQuickMenu(); window._hideEditServiceQuickMenu = null; }
        novaMarcacaoSelectedServices.splice(idx, 1);
        novaMarcacaoRenderSelectedServices();
        novaMarcacaoUpdateEndTimeAndTotal();
        if (novaMarcacaoSelectedServices.length === 0) {
            document.getElementById('novaMarcacaoServiceSelected').classList.add('d-none');
            document.getElementById('novaMarcacaoServicesList').classList.remove('d-none');
        }
    }

    var eventDetailOriginalStartAt = null;
    var eventDetailOriginalEndAt = null;
    var eventDetailWasSaved = false;
    function populateEventDetailEditModal(data) {
        eventDetailCurrentData = data;
        eventDetailOriginalStartAt = data.start_at || null;
        eventDetailOriginalEndAt = data.end_at || null;
        eventDetailSelectedServices = [];
        var id = data.id;
        document.getElementById('eventDetailEditId').value = id;
        document.getElementById('eventDetailEditUserId').value = data.user_id || '';
        document.getElementById('eventDetailEditStart').value = data.start_at || '';
        document.getElementById('eventDetailEditEnd').value = data.end_at || '';
        var startDate = data.start_at ? new Date(data.start_at) : null;
        var endDate = data.end_at ? new Date(data.end_at) : null;
        var daysPt = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var monthsPtShort = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        if (startDate) {
            var dayStr = daysPt[startDate.getDay()] + ', ' + startDate.getDate() + ' ' + monthsPtShort[startDate.getMonth()];
            var timeStr = String(startDate.getHours()).padStart(2, '0') + ':' + String(startDate.getMinutes()).padStart(2, '0');
            var min = startDate.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            var timeSlotForDropdown = String(startDate.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            document.getElementById('eventDetailEditTitleDay').textContent = dayStr + ' · ';
            document.getElementById('eventDetailTimeToggle').textContent = timeStr;
            eventDetailPopulateTimeOptions(timeSlotForDropdown);
        } else {
            document.getElementById('eventDetailEditTitleDay').textContent = '—';
            document.getElementById('eventDetailTimeToggle').textContent = '—';
            eventDetailPopulateTimeOptions('');
        }
        var agentInfo = agendaAgentInfo[String(data.user_id)] || { name: data.user_name || '—', email: '', avatarUrl: data.user_avatar_url || '' };
        document.getElementById('eventDetailAgentName').textContent = agentInfo.name || '—';
        document.getElementById('eventDetailAgentEmail').textContent = agentInfo.email || '—';
        document.getElementById('eventDetailAgentLink').href = (function() { var info = agendaAgentInfo[String(data.user_id || '')]; return (info && info.agentId) ? (agendaEquipaBaseUrl + '/' + info.agentId) : '#'; })();
        if (agentInfo.avatarUrl) {
            document.getElementById('eventDetailAgentAvatar').src = agentInfo.avatarUrl;
            document.getElementById('eventDetailAgentAvatar').style.display = 'block';
        } else {
            document.getElementById('eventDetailAgentAvatar').style.display = 'none';
        }
        var statusVal = data.status || 'agendado';
        document.getElementById('eventDetailStatus').value = statusVal;
        var statusLabels = { agendado: 'Agendado', confirmado: 'Confirmado', chegou: 'Chegou', iniciado: 'Iniciado', faltou: 'Faltou', cancelado: 'Cancelado' };
        var statusIcons = { agendado: 'ph-clock', confirmado: 'ph-check', chegou: 'ph-map-pin', iniciado: 'ph-play', faltou: 'ph-prohibit', cancelado: 'ph-x-circle' };
        document.getElementById('eventDetailStatusLabel').textContent = statusLabels[statusVal] || statusVal;
        var iconEl = document.getElementById('eventDetailStatusIcon');
        if (iconEl) {
            var ic = iconEl.querySelector('i');
            if (ic) ic.className = 'me-2 ph ' + (statusIcons[statusVal] || 'ph-clock');
        }
        document.getElementById('eventDetailCancelReason').value = data.cancellation_reason || '';
        document.getElementById('eventDetailCancelReasonWrap').classList.toggle('d-none', statusVal !== 'cancelado');
        document.getElementById('eventDetailObservacoes').value = data.description || '';
        var hasVisitLead = !!(data.visit || data.lead);
        var hasClient = data.client_id && data.client_name;
        document.getElementById('eventDetailClientSearchWrap').classList.toggle('d-none', hasVisitLead || hasClient);
        document.getElementById('eventDetailClientResults').classList.toggle('d-none', hasVisitLead || hasClient);
        document.getElementById('eventDetailClientSelected').classList.add('d-none');
        document.getElementById('eventDetailVisitLeadBlock').classList.add('d-none');
        document.getElementById('eventDetailCreateClientBtn').classList.toggle('d-none', hasVisitLead || hasClient);
        if (hasVisitLead) {
            var block = document.getElementById('eventDetailVisitLeadBlock');
            block.classList.remove('d-none');
            block.innerHTML = '';
            if (data.visit) {
                block.innerHTML = '<h6 class="nova-marcacao-section-title">Cliente (Visita)</h6><div class="nova-marcacao-person"><div><strong>' + (data.visit.client_name || '—') + '</strong></div></div>' +
                    '<div class="mt-2"><a href="' + (data.visit.opportunity_id ? '{{ url("opportunities") }}/' + data.visit.opportunity_id : '#') + '" class="btn btn-sm btn-outline-primary"><i class="ph ph-briefcase me-1"></i>Ficha da Oportunidade</a></div>';
            } else if (data.lead) {
                block.innerHTML = '<h6 class="nova-marcacao-section-title">Lead</h6><div class="nova-marcacao-person"><div><strong>' + (data.lead.name || '—') + '</strong><span class="d-block small text-muted">' + [data.lead.email, data.lead.phone].filter(Boolean).join(' · ') + '</span></div></div>' +
                    '<div class="mt-2"><a href="{{ url("leads") }}/' + data.lead.id + '" class="btn btn-sm btn-outline-primary"><i class="ph ph-file-text me-1"></i>Ficha da Lead</a></div>';
            }
        } else if (data.client_id && data.client_name) {
            eventDetailSelectedClient = { id: data.client_id, name: data.client_name, email: data.client_email || '', avatar_url: data.client_avatar_url || '' };
            document.getElementById('eventDetailClientSelectedName').textContent = data.client_name;
            document.getElementById('eventDetailClientSelectedEmail').textContent = data.client_email || '—';
            if (data.client_avatar_url) {
                document.getElementById('eventDetailClientAvatar').src = data.client_avatar_url;
                document.getElementById('eventDetailClientAvatar').classList.remove('d-none');
                document.getElementById('eventDetailClientAvatarFallback').classList.add('d-none');
            } else {
                var initials = (data.client_name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                document.getElementById('eventDetailClientAvatarFallback').textContent = initials;
                document.getElementById('eventDetailClientAvatarFallback').classList.remove('d-none');
                document.getElementById('eventDetailClientAvatar').classList.add('d-none');
            }
            document.getElementById('eventDetailClientSelected').classList.remove('d-none');
            document.getElementById('eventDetailCreateClientBtn').classList.add('d-none');
        } else {
            eventDetailSelectedClient = null;
        }
        document.getElementById('eventDetailServicesCol').classList.toggle('d-none', data.event_type !== 'marcacao');
        if (data.event_type === 'marcacao') {
            (data.event_services || []).forEach(function(s) {
                var dur = s.duration || 60;
                var pr = parseFloat(s.price) || 0;
                var origPr = s.original_price != null ? parseFloat(s.original_price) : pr;
                var extras = (s.extras || []).map(function(e) {
                    return { id: e.extra_id, name: e.name, duration: e.duration || 0, price: e.price || 0, formatted_duration: e.formatted_duration || (e.duration || 0) + ' min', formatted_price: e.formatted_price || (e.price || 0).toFixed(2).replace('.', ',') + ' €' };
                });
                eventDetailSelectedServices.push({
                    service_id: s.id,
                    name: s.name,
                    duration: dur,
                    price: pr,
                    original_price: origPr,
                    formatted_duration: (s.formatted_duration || dur + ' min'),
                    formatted_price: s.formatted_price || (pr.toFixed(2).replace('.', ',') + ' €'),
                    color: s.color || '#6c757d',
                    available_extras: [],
                    extras: extras
                });
            });
            eventDetailRenderSelectedServices();
            eventDetailUpdateTotal();
            eventDetailUpdateEndTime();
            document.getElementById('eventDetailServicesList').innerHTML = '<div class="text-muted small">A carregar...</div>';
            fetch(agendaMembersServicesUrl + '/' + (data.user_id || '{{ auth()->id() }}') + '/services', { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(svcData) {
                    eventDetailServicesData = svcData;
                    eventDetailSelectedServices.forEach(function(item) {
                        var availableExtras = [];
                        (svcData.categories || []).forEach(function(cat) {
                            (cat.services || []).forEach(function(svc) {
                                if (String(svc.id) === String(item.service_id)) availableExtras = svc.extras || [];
                            });
                        });
                        item.available_extras = availableExtras;
                    });
                    eventDetailRenderSelectedServices();
                    var html = '';
                    (svcData.categories || []).forEach(function(cat) {
                        html += '<div class="nova-marcacao-services-category">' + (cat.name || 'Outros') + '</div>';
                        var color = cat.color || '#6c757d';
                        (cat.services || []).forEach(function(s) {
                            var sFormattedDur = s.formatted_duration || (s.duration || 60) + ' min';
                            var sFormattedPrice = s.formatted_price || '';
                            var sPrice = (s.price != null && s.price !== '') ? parseFloat(s.price) : 0;
                            html += '<div class="nova-marcacao-service-item event-detail-service-item" data-service-id="' + s.id + '" data-duration="' + (s.duration || 60) + '" data-name="' + (s.name || '').replace(/"/g, '&quot;') + '" data-price="' + sPrice + '" data-formatted-duration="' + (sFormattedDur || '').replace(/"/g, '&quot;') + '" data-formatted-price="' + (sFormattedPrice || '').replace(/"/g, '&quot;') + '" data-color="' + (color || '#6c757d').replace(/"/g, '&quot;') + '" style="border-left-color:' + color + '">';
                            html += '<div class="nova-marcacao-service-item-left"><div class="nova-marcacao-service-item-name">' + (s.name || '') + '</div><div class="nova-marcacao-service-item-duration"><i class="ph ph-clock me-1"></i>' + sFormattedDur + '</div></div>';
                            html += '<div class="nova-marcacao-service-item-price">' + sFormattedPrice + '</div></div>';
                        });
                    });
                    document.getElementById('eventDetailServicesList').innerHTML = html || '<div class="text-muted small">Nenhum serviço disponível.</div>';
                    document.getElementById('eventDetailServicesList').querySelectorAll('.event-detail-service-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var sid = this.dataset.serviceId;
                            if (eventDetailSelectedServices.some(function(s) { return String(s.service_id) === sid; })) return;
                            var dur = parseInt(this.dataset.duration, 10) || 60;
                            var priceNum = parseFloat(this.dataset.price) || 0;
                            var availableExtras = [];
                            (eventDetailServicesData.categories || []).forEach(function(cat) {
                                (cat.services || []).forEach(function(svc) {
                                    if (String(svc.id) === sid) availableExtras = svc.extras || [];
                                });
                            });
                            eventDetailSelectedServices.push({
                                service_id: sid, name: this.dataset.name || '', duration: dur, price: priceNum,
                                original_price: priceNum,
                                formatted_duration: this.dataset.formattedDuration || dur + ' min',
                                formatted_price: this.dataset.formattedPrice || (priceNum.toFixed(2).replace('.', ',') + ' €'),
                                color: this.dataset.color || '#6c757d',
                                available_extras: availableExtras,
                                extras: []
                            });
                            eventDetailRenderSelectedServices();
                            eventDetailUpdateTotal();
                            eventDetailUpdateEndTime();
                            document.getElementById('eventDetailServicesList').classList.add('d-none');
                            document.getElementById('eventDetailServicesListCancelWrap').classList.add('d-none');
                            document.getElementById('eventDetailServiceSelected').classList.remove('d-none');
                        });
                    });
                    if (eventDetailSelectedServices.length > 0) {
                        document.getElementById('eventDetailServicesList').classList.add('d-none');
                        document.getElementById('eventDetailServicesListCancelWrap').classList.add('d-none');
                        document.getElementById('eventDetailServiceSelected').classList.remove('d-none');
                    }
                })
                .catch(function() {
                    document.getElementById('eventDetailServicesList').innerHTML = '<div class="text-danger small">Erro ao carregar serviços.</div>';
                });
        }
    }

    var eventDetailSelectedClient = null;
    var eventDetailServicesData = null;

    function eventDetailRenderSelectedServices() {
        var container = document.getElementById('eventDetailSelectedServicesList');
        if (!container) return;
        var titleEl = document.querySelector('#eventDetailServiceSelected .nova-marcacao-services-selected-title');
        if (eventDetailSelectedServices.length === 0) {
            container.innerHTML = '';
            if (titleEl) titleEl.textContent = 'Serviços selecionados';
            return;
        }
        if (titleEl) titleEl.textContent = eventDetailSelectedServices.length === 1 ? 'Serviço selecionado' : 'Serviços selecionados';
        var html = eventDetailSelectedServices.map(function(item, idx) {
            var extrasLine = (item.extras && item.extras.length)
                ? '<div class="small text-muted mt-1 event-detail-extras-line">' +
                    item.extras.map(function(e, eIdx) {
                        var priceText = e.formatted_price || ((parseFloat(e.price) || 0).toFixed(2).replace('.', ',') + ' €');
                        var durText = e.formatted_duration || ((e.duration || 0) + ' min');
                        return '<span class="badge rounded-pill text-bg-light me-1">' +
                            '+ ' + (e.name || '') + ' (' + priceText + ' · ' + durText + ')' +
                            '<button type="button" class="btn btn-link btn-sm p-0 ms-1 eventDetailRemoveExtraBtn" data-idx="' + idx + '" data-extra-index="' + eIdx + '" aria-label="Remover extra"><i class="ph ph-x"></i></button>' +
                            '</span>';
                    }).join('') +
                  '</div>'
                : '';
            var hasAvailableExtras = (item.available_extras && item.available_extras.length > 0);
            var addExtrasBtn = hasAvailableExtras ? '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm eventDetailAddExtrasBtn" data-idx="' + idx + '" title="Adicionar extras"><i class="ph ph-plus-circle"></i></button>' : '';
            var origP = item.original_price != null ? parseFloat(item.original_price) : NaN;
            var currP = item.price != null ? parseFloat(item.price) : NaN;
            var showStrikethrough = !isNaN(origP) && currP !== origP;
            var priceBlock = showStrikethrough
                ? '<span class="text-danger text-decoration-line-through small me-1">' + (origP.toFixed(2).replace('.', ',') + ' €') + '</span><span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>'
                : '<span class="nova-marcacao-service-item-price">' + (item.formatted_price || '') + '</span>';
            return '<div class="nova-marcacao-service-item nova-marcacao-service-selected-card d-flex justify-content-between align-items-center mb-2" data-idx="' + idx + '" style="border-left-color:' + (item.color || '#6c757d') + '">' +
                '<div class="nova-marcacao-service-item-left">' +
                '<div class="nova-marcacao-service-item-name">' + (item.name || '') + '</div>' +
                '<div class="nova-marcacao-service-item-duration"><i class="ph ph-clock me-1"></i>' + (item.formatted_duration || item.duration + ' min') + '</div>' + extrasLine +
                '</div>' +
                '<div class="d-flex align-items-center gap-2 justify-content-end">' +
                '<div class="d-flex gap-1">' + addExtrasBtn +
                '<button type="button" class="btn btn-outline-secondary btn-icon btn-sm eventDetailEditServiceBtn" data-idx="' + idx + '" title="Alterar opções"><i class="ph ph-pencil-simple"></i></button>' +
                '<button type="button" class="btn btn-outline-danger btn-icon btn-sm eventDetailDeleteServiceBtn" data-idx="' + idx + '" title="Eliminar"><i class="ph ph-trash"></i></button>' +
                '</div>' +
                priceBlock +
                '</div></div></div>';
        }).join('');
        container.innerHTML = html;
        container.querySelectorAll('.eventDetailEditServiceBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); eventDetailOpenEditQuickMenu(e, parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.eventDetailDeleteServiceBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); eventDetailDeleteService(parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.eventDetailAddExtrasBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) { e.stopPropagation(); eventDetailOpenAddExtrasQuickMenu(e, parseInt(this.dataset.idx, 10)); });
        });
        container.querySelectorAll('.eventDetailRemoveExtraBtn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var sIdx = parseInt(this.dataset.idx, 10);
                var exIdx = parseInt(this.dataset.extraIndex, 10);
                if (!isNaN(sIdx) && !isNaN(exIdx) && eventDetailSelectedServices[sIdx] && Array.isArray(eventDetailSelectedServices[sIdx].extras)) {
                    eventDetailSelectedServices[sIdx].extras.splice(exIdx, 1);
                    eventDetailRenderSelectedServices();
                    eventDetailUpdateTotal();
                    eventDetailUpdateEndTime();
                }
            });
        });
    }

    function eventDetailOpenAddExtrasQuickMenu(evt, idx) {
        var item = eventDetailSelectedServices[idx];
        if (!item || !item.available_extras || !item.available_extras.length) return;
        var addedIds = (item.extras || []).map(function(e) { return e.id; });
        var toShow = item.available_extras.filter(function(e) { return addedIds.indexOf(e.id) === -1; });
        if (!toShow.length) {
            showToast('Não há mais extras disponíveis para este serviço.', 'info');
            return;
        }
        var popup = document.getElementById('eventDetailAddExtrasQuickMenu');
        if (!popup) return;
        var modalContent = document.getElementById('eventDetailEditModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        function hide() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', ch);
            document.removeEventListener('keydown', eh);
        }
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        popup.innerHTML = '<div class="edit-service-header"><h6>Adicionar extra</h6><button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div><div class="edit-service-body"><div class="list-group list-group-flush" id="eventDetailAddExtrasList"></div></div>';
        popup.querySelector('.edit-service-close').addEventListener('click', hide);
        var list = document.getElementById('eventDetailAddExtrasList');
        toShow.forEach(function(ex) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            a.innerHTML = '<span>' + (ex.name || '').replace(/</g, '&lt;') + '</span><span class="small text-muted">' + (ex.formatted_price || ex.price + ' €') + ' · ' + (ex.formatted_duration || ex.duration + ' min') + '</span>';
            a.addEventListener('click', function(e) {
                e.preventDefault();
                if (!eventDetailSelectedServices[idx].extras) eventDetailSelectedServices[idx].extras = [];
                eventDetailSelectedServices[idx].extras.push({ id: ex.id, name: ex.name, duration: ex.duration || 0, price: ex.price || 0, formatted_duration: ex.formatted_duration || (ex.duration || 0) + ' min', formatted_price: ex.formatted_price || (ex.price || 0).toFixed(2).replace('.', ',') + ' €' });
                hide();
                eventDetailRenderSelectedServices();
                eventDetailUpdateTotal();
                eventDetailUpdateEndTime();
            });
            list.appendChild(a);
        });
        popup.classList.add('is-open');
        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
        var left = container ? (rect.left - container.left) : (rect.left || 0);
        var top = container ? (rect.bottom + offset - container.top) : ((rect.bottom || 0) + offset);
        popup.style.left = (left || 0) + 'px';
        popup.style.top = (top || 0) + 'px';
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    function eventDetailUpdateTotal() {
        var total = eventDetailSelectedServices.reduce(function(sum, s) {
            var p = (parseFloat(s.price) || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (parseFloat(e.price) || 0); }, 0);
            return sum + p;
        }, 0);
        document.getElementById('eventDetailTotalPrice').textContent = total.toFixed(2).replace('.', ',') + ' €';
    }

    function eventDetailUpdateEndTime() {
        var startStr = document.getElementById('eventDetailEditStart').value;
        if (!startStr) return;
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        var start = new Date(startStr);
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        document.getElementById('eventDetailEditEnd').value = endStr;
    }

    function eventDetailApplyNewStartTime(newTimeStr) {
        var startStr = document.getElementById('eventDetailEditStart').value;
        var endStr = document.getElementById('eventDetailEditEnd').value;
        if (!startStr) return;
        var start = new Date(startStr);
        var parts = (newTimeStr || '').match(/^(\d{1,2}):(\d{2})/);
        if (!parts) return;
        start.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        if (totalDur === 0 && endStr) {
            var oldStart = new Date(startStr);
            var oldEnd = new Date(endStr);
            totalDur = (oldEnd.getTime() - oldStart.getTime()) / 60000;
        }
        if (totalDur < 1) totalDur = 60;
        var end = new Date(start.getTime() + totalDur * 60 * 1000);
        var startIso = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0') + 'T' + String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        var endIso = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        document.getElementById('eventDetailEditStart').value = startIso;
        document.getElementById('eventDetailEditEnd').value = endIso;
        var daysPt = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var monthsPtShort = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        document.getElementById('eventDetailEditTitleDay').textContent = daysPt[start.getDay()] + ', ' + start.getDate() + ' ' + monthsPtShort[start.getMonth()] + ' · ';
        document.getElementById('eventDetailTimeToggle').textContent = String(start.getHours()).padStart(2, '0') + ':' + String(start.getMinutes()).padStart(2, '0');
        if (eventDetailCurrentData) {
            eventDetailCurrentData.start_at = startIso;
            eventDetailCurrentData.end_at = endIso;
        }
        var evId = document.getElementById('eventDetailEditId').value;
        if (evId && typeof calendar !== 'undefined') {
            var ev = calendar.getEventById(evId);
            if (ev) {
                ev.setStart(start);
                ev.setEnd(end);
            }
        }
        var dd = document.getElementById('eventDetailTimeToggle');
        if (dd && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(dd);
            if (inst) inst.hide();
        }
    }

    var eventDetailEditServiceIndex = -1;
    function eventDetailOpenEditQuickMenu(evt, idx) {
        eventDetailEditServiceIndex = idx;
        var item = eventDetailSelectedServices[idx];
        if (!item) return;
        var popup = document.getElementById('novaMarcacaoEditServiceQuickMenu');
        if (!popup) return;
        function hide() { popup.classList.remove('is-open'); popup.innerHTML = ''; document.removeEventListener('click', ch); document.removeEventListener('keydown', eh); window._hideEditServiceQuickMenu = null; }
        window._hideEditServiceQuickMenu = hide;
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        var modalContent = document.getElementById('eventDetailEditModal')?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        popup.innerHTML = '<div class="edit-service-header"><h6>Alterar opções do serviço</h6><button type="button" class="edit-service-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>' +
            '<div class="edit-service-body"><div class="mb-2"><label class="form-label small">Duração (minutos)</label><input type="number" class="form-control form-control-sm edDur" min="1" value="' + (item.duration || '') + '"></div>' +
            '<div class="mb-0"><label class="form-label small">Preço (€)</label><input type="number" class="form-control form-control-sm edPrice" min="0" step="0.01" value="' + (item.price != null ? item.price : '') + '"></div></div>' +
            '<div class="edit-service-footer"><button type="button" class="btn btn-light btn-sm edCancel">Cancelar</button><button type="button" class="btn btn-primary btn-sm edSave">Guardar</button></div>';
        popup.querySelector('.edit-service-close').addEventListener('click', hide);
        popup.querySelector('.edCancel').addEventListener('click', hide);
        popup.querySelector('.edSave').addEventListener('click', function() {
            var d = parseInt(popup.querySelector('.edDur').value, 10);
            var p = parseFloat(popup.querySelector('.edPrice').value);
            if (eventDetailSelectedServices[idx]) {
                if (!isNaN(d) && d > 0) eventDetailSelectedServices[idx].duration = d;
                if (!isNaN(p) && p >= 0) { eventDetailSelectedServices[idx].price = p; eventDetailSelectedServices[idx].formatted_price = p.toFixed(2).replace('.', ',') + ' €'; }
                eventDetailSelectedServices[idx].formatted_duration = (eventDetailSelectedServices[idx].duration || 0) + ' min';
                eventDetailRenderSelectedServices();
                eventDetailUpdateTotal();
                eventDetailUpdateEndTime();
            }
            hide();
        });
        var rect = evt.target.closest('button')?.getBoundingClientRect() || { left: evt.clientX, bottom: evt.clientY };
        var offset = 8;
        requestAnimationFrame(function() {
            popup.classList.add('is-open');
            var popupRect = popup.getBoundingClientRect();
            var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
            var left, top;
            if (container) {
                left = (rect.left || evt.clientX) - container.left;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset - container.top;
            } else {
                left = rect.left || evt.clientX;
                top = (rect.bottom !== undefined ? rect.bottom : evt.clientY) + offset;
            }
            var vw = container ? container.width : window.innerWidth;
            var vh = container ? container.height : window.innerHeight;
            var maxLeft = vw - popupRect.width - offset;
            var maxTop = vh - popupRect.height - offset;
            left = Math.max(offset, Math.min(left, maxLeft));
            top = Math.max(offset, Math.min(top, maxTop));
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
        });
        setTimeout(function() { document.addEventListener('click', ch); document.addEventListener('keydown', eh); }, 0);
    }

    function eventDetailDeleteService(idx) {
        if (typeof window._hideEditServiceQuickMenu === 'function') { window._hideEditServiceQuickMenu(); window._hideEditServiceQuickMenu = null; }
        eventDetailSelectedServices.splice(idx, 1);
        eventDetailRenderSelectedServices();
        eventDetailUpdateTotal();
        eventDetailUpdateEndTime();
        if (eventDetailSelectedServices.length === 0) {
            document.getElementById('eventDetailServiceSelected').classList.add('d-none');
            document.getElementById('eventDetailServicesList').classList.remove('d-none');
        }
    }

    @php
        $agentInfoMap = collect($users ?? [])->mapWithKeys(function($u) {
            $a = $u->agent ?? null;
            $avatarNum = $a ? (($a->id ?? 1) % 9) + 1 : 1;
            $avatarUrl = $a && $a->avatar ? asset('storage/' . $a->avatar) : asset('template/img/avatars/avatar-' . $avatarNum . '.webp');
            return ['' . $u->id => ['name' => $u->name, 'email' => $u->email ?? '', 'avatarUrl' => $avatarUrl, 'agentId' => $a ? $a->id : null]];
        });
        $me = auth()->user();
        if ($me && !$agentInfoMap->has('' . $me->id)) {
            $a = $me->agent ?? null;
            $avatarNum = $a ? (($a->id ?? 1) % 9) + 1 : 1;
            $avatarUrl = $a && $a->avatar ? asset('storage/' . $a->avatar) : asset('template/img/avatars/avatar-' . $avatarNum . '.webp');
            $agentInfoMap->put('' . $me->id, ['name' => $me->name, 'email' => $me->email ?? '', 'avatarUrl' => $avatarUrl, 'agentId' => $a ? $a->id : null]);
        }
    @endphp
    var agendaAgentInfo = @json($agentInfoMap->all());

    function openNovaMarcacaoModal(startStr, endStr, resourceId) {
        var agentId = resourceId || '{{ auth()->id() }}';
        document.getElementById('novaMarcacaoAgentId').value = agentId;
        document.getElementById('novaMarcacaoStart').value = startStr;
        document.getElementById('novaMarcacaoEnd').value = endStr;
        document.getElementById('novaMarcacaoObservacoes').value = '';
        novaMarcacaoSelectedClient = null;
        document.getElementById('novaMarcacaoClientSelected').classList.add('d-none');
        document.getElementById('novaMarcacaoClientSearchWrap').classList.remove('d-none');
        document.getElementById('novaMarcacaoCreateClientBtn').classList.remove('d-none');
        document.getElementById('novaMarcacaoClientSearch').value = '';
        document.getElementById('novaMarcacaoClientResults').innerHTML = '';
        var agentInfo = agendaAgentInfo[String(agentId)] || { name: '—', email: '', avatarUrl: '' };
        if (!agentInfo.name || agentInfo.name === '—') {
            var resources = calendar.getResources();
            for (var i = 0; i < resources.length; i++) {
                if (String(resources[i].id) === String(agentId)) {
                    agentInfo.name = resources[i].title || '—';
                    agentInfo.avatarUrl = resources[i].extendedProps?.avatarUrl || agentInfo.avatarUrl;
                    break;
                }
            }
        }
        if (agentInfo.name === '—' && agentId === '{{ auth()->id() }}') {
            agentInfo = { name: '{{ auth()->user()->name ?? "Eu" }}', email: '{{ auth()->user()->email ?? "" }}', avatarUrl: agentInfo.avatarUrl || '' };
        }
        document.getElementById('novaMarcacaoAgentName').textContent = agentInfo.name || '—';
        document.getElementById('novaMarcacaoAgentEmail').textContent = agentInfo.email || '—';
        document.getElementById('novaMarcacaoAgentLink').href = (function() { var info = agendaAgentInfo[String(agentId)]; return (info && info.agentId) ? (agendaEquipaBaseUrl + '/' + info.agentId) : '#'; })();
        if (agentInfo.avatarUrl) {
            document.getElementById('novaMarcacaoAgentAvatar').src = agentInfo.avatarUrl;
            document.getElementById('novaMarcacaoAgentAvatar').style.display = 'block';
        } else {
            document.getElementById('novaMarcacaoAgentAvatar').style.display = 'none';
        }
        var startD = new Date(startStr);
        var endD = new Date(endStr);
        var daysPt = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        var monthsPtShort = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        var timeStr = String(startD.getHours()).padStart(2, '0') + ':' + String(startD.getMinutes()).padStart(2, '0');
        var min = startD.getMinutes();
        var m = Math.round(min / 15) * 15;
        if (m === 60) { m = 0; }
        var timeSlotForDropdown = String(startD.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        document.getElementById('novaMarcacaoEditTitleDay').textContent = daysPt[startD.getDay()] + ', ' + startD.getDate() + ' ' + monthsPtShort[startD.getMonth()] + ' · ';
        document.getElementById('novaMarcacaoTimeToggle').textContent = timeStr;
        novaMarcacaoPopulateTimeOptions(timeSlotForDropdown);
        document.getElementById('novaMarcacaoServicesList').innerHTML = '<div class="text-muted small">A carregar serviços...</div>';
        document.getElementById('novaMarcacaoServicesList').classList.remove('d-none');
        document.getElementById('novaMarcacaoServiceSelected').classList.add('d-none');
        novaMarcacaoServicesData = null;
        novaMarcacaoSelectedServices = [];
        fetch(agendaMembersServicesUrl + '/' + agentId + '/services', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                novaMarcacaoServicesData = data;
                var html = '';
                (data.categories || []).forEach(function(cat) {
                    html += '<div class="nova-marcacao-services-category">' + (cat.name || 'Outros') + '</div>';
                    var color = cat.color || '#6c757d';
                    (cat.services || []).forEach(function(s) {
                        var sFormattedDur = s.formatted_duration || (s.duration || 60) + ' min';
                        var sFormattedPrice = s.formatted_price || '';
                        var sPrice = (s.price != null && s.price !== '') ? parseFloat(s.price) : 0;
                        html += '<div class="nova-marcacao-service-item" data-service-id="' + s.id + '" data-duration="' + (s.duration || 60) + '" data-name="' + (s.name || '').replace(/"/g, '&quot;') + '" data-price="' + sPrice + '" data-formatted-duration="' + (sFormattedDur || '').replace(/"/g, '&quot;') + '" data-formatted-price="' + (sFormattedPrice || '').replace(/"/g, '&quot;') + '" data-color="' + (color || '#6c757d').replace(/"/g, '&quot;') + '" style="border-left-color:' + color + '">';
                        html += '<div class="nova-marcacao-service-item-left"><div class="nova-marcacao-service-item-name">' + (s.name || '') + '</div><div class="nova-marcacao-service-item-duration"><i class="ph ph-clock me-1"></i>' + sFormattedDur + '</div></div>';
                        html += '<div class="nova-marcacao-service-item-price">' + sFormattedPrice + '</div></div>';
                    });
                });
                document.getElementById('novaMarcacaoServicesList').innerHTML = html || '<div class="text-muted small">Nenhum serviço disponível.</div>';
                document.getElementById('novaMarcacaoServicesList').querySelectorAll('.nova-marcacao-service-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var sid = this.dataset.serviceId;
                        if (novaMarcacaoSelectedServices.some(function(s) { return String(s.service_id) === sid; })) return;
                        var dur = parseInt(this.dataset.duration, 10) || 60;
                        var priceNum = parseFloat(this.dataset.price) || 0;
                        var availableExtras = [];
                        (novaMarcacaoServicesData.categories || []).forEach(function(cat) {
                            (cat.services || []).forEach(function(svc) {
                                if (String(svc.id) === sid) availableExtras = svc.extras || [];
                            });
                        });
                        novaMarcacaoSelectedServices.push({
                            service_id: sid,
                            name: this.dataset.name || '',
                            duration: dur,
                            price: priceNum,
                            original_price: priceNum,
                            formatted_duration: this.dataset.formattedDuration || dur + ' min',
                            formatted_price: this.dataset.formattedPrice || (priceNum.toFixed(2).replace('.', ',') + ' €'),
                            color: this.dataset.color || '#6c757d',
                            available_extras: availableExtras,
                            extras: []
                        });
                        novaMarcacaoRenderSelectedServices();
                        novaMarcacaoUpdateEndTimeAndTotal();
                        document.getElementById('novaMarcacaoServicesList').classList.add('d-none');
                        document.getElementById('novaMarcacaoServicesListCancelWrap').classList.add('d-none');
                        document.getElementById('novaMarcacaoServiceSelected').classList.remove('d-none');
                    });
                });
            })
            .catch(function() {
                document.getElementById('novaMarcacaoServicesList').innerHTML = '<div class="text-danger small">Erro ao carregar serviços.</div>';
            });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('novaMarcacaoModal')).show();
    }

    document.getElementById('novaMarcacaoClientSearch').addEventListener('input', (function() {
        var t;
        return function() {
            clearTimeout(t);
            var q = this.value.trim();
            if (q.length < 1) {
                document.getElementById('novaMarcacaoClientResults').innerHTML = '';
                return;
            }
            t = setTimeout(function() {
                document.getElementById('novaMarcacaoClientResults').innerHTML = '<div class="text-muted small">A pesquisar...</div>';
                fetch(agendaClientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(clients) {
                        if (!clients.length) {
                            document.getElementById('novaMarcacaoClientResults').innerHTML = '<div class="text-muted small">Nenhum cliente encontrado.</div>';
                            return;
                        }
                        var html = clients.map(function(c) {
                            var dataAttrs = 'data-id="' + c.id + '" data-name="' + (c.name || '').replace(/"/g, '&quot;') + '" data-email="' + (c.email || '').replace(/"/g, '&quot;') + '" data-avatar="' + (c.avatar_url || '').replace(/"/g, '&quot;') + '"';
                            return '<div class="nova-marcacao-client-item" ' + dataAttrs + '>' + (c.name || '') + (c.email ? ' <small class="text-muted">' + c.email + '</small>' : '') + '</div>';
                        }).join('');
                        document.getElementById('novaMarcacaoClientResults').innerHTML = html;
                        document.getElementById('novaMarcacaoClientResults').querySelectorAll('.nova-marcacao-client-item').forEach(function(el) {
                            el.addEventListener('click', function() {
                                var name = this.dataset.name || '';
                                var email = this.dataset.email || '';
                                var avatarUrl = this.dataset.avatar || '';
                                novaMarcacaoSelectedClient = { id: this.dataset.id, name: name, email: email, avatar_url: avatarUrl };
                                document.getElementById('novaMarcacaoClientSelectedName').textContent = name;
                                document.getElementById('novaMarcacaoClientSelectedEmail').textContent = email || '—';
                                if (avatarUrl) {
                                    document.getElementById('novaMarcacaoClientAvatar').src = avatarUrl;
                                    document.getElementById('novaMarcacaoClientAvatar').classList.remove('d-none');
                                    document.getElementById('novaMarcacaoClientAvatarFallback').classList.add('d-none');
                                } else {
                                    document.getElementById('novaMarcacaoClientAvatar').classList.add('d-none');
                                    var initials = (name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                                    document.getElementById('novaMarcacaoClientAvatarFallback').textContent = initials;
                                    document.getElementById('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
                                }
                                document.getElementById('novaMarcacaoClientSelected').classList.remove('d-none');
                                document.getElementById('novaMarcacaoClientSearchWrap').classList.add('d-none');
                                document.getElementById('novaMarcacaoClientResults').innerHTML = '';
                                document.getElementById('novaMarcacaoClientSearch').value = '';
                                document.getElementById('novaMarcacaoClientCancelBtn').classList.add('d-none');
                                document.getElementById('novaMarcacaoCreateClientBtn').classList.add('d-none');
                                window._novaMarcacaoPreviousClient = null;
                            });
                        });
                    })
                    .catch(function() {
                        document.getElementById('novaMarcacaoClientResults').innerHTML = '<div class="text-danger small">Erro ao pesquisar.</div>';
                    });
            }, 300);
        };
    })());

    document.getElementById('novaMarcacaoClientClear').addEventListener('click', function() {
        var prev = novaMarcacaoSelectedClient ? { id: novaMarcacaoSelectedClient.id, name: novaMarcacaoSelectedClient.name, email: novaMarcacaoSelectedClient.email || '', avatar_url: novaMarcacaoSelectedClient.avatar_url || '' } : null;
        novaMarcacaoSelectedClient = null;
        document.getElementById('novaMarcacaoClientSelected').classList.add('d-none');
        document.getElementById('novaMarcacaoClientSearchWrap').classList.remove('d-none');
        document.getElementById('novaMarcacaoClientResults').innerHTML = '';
        document.getElementById('novaMarcacaoCreateClientBtn').classList.remove('d-none');
        if (prev) {
            window._novaMarcacaoPreviousClient = prev;
            document.getElementById('novaMarcacaoClientCancelBtn').classList.remove('d-none');
        }
    });
    function openCreateClientQuickMenu(context, evt) {
        var popup = document.getElementById('agendaCreateClientQuickMenu');
        if (!popup) return;
        var modalEl = context === 'novaMarcacao' ? document.getElementById('novaMarcacaoModal') : document.getElementById('eventDetailEditModal');
        var modalContent = modalEl?.querySelector('.modal-content');
        if (modalContent) modalContent.appendChild(popup);
        function hide() {
            popup.classList.remove('is-open');
            popup.innerHTML = '';
            document.removeEventListener('click', ch);
            document.removeEventListener('keydown', eh);
        }
        function ch(e) { if (popup.contains(e.target)) return; hide(); }
        function eh(e) { if (e.key === 'Escape') { e.stopPropagation(); hide(); } }
        popup.innerHTML = '<div class="create-client-header">' +
            '<h6>Novo cliente</h6>' +
            '<button type="button" class="create-client-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>' +
            '</div>' +
            '<div class="create-client-body">' +
            '<div class="mb-2"><label class="form-label small">Nome <span class="text-danger">*</span></label>' +
            '<input type="text" class="form-control form-control-sm create-client-name" placeholder="Nome do cliente" required></div>' +
            '<div class="mb-2"><label class="form-label small">Email <span class="text-danger">*</span></label>' +
            '<input type="email" class="form-control form-control-sm create-client-email" placeholder="email@exemplo.pt" required></div>' +
            '<div class="mb-0"><label class="form-label small">Telefone</label>' +
            '<input type="tel" class="form-control form-control-sm create-client-phone" placeholder="+351 912 345 678"></div>' +
            '</div>' +
            '<div class="create-client-footer">' +
            '<button type="button" class="btn btn-light btn-sm create-client-cancel">Cancelar</button>' +
            '<button type="button" class="btn btn-primary btn-sm create-client-submit">Criar</button>' +
            '</div>';
        popup.querySelector('.create-client-close').addEventListener('click', hide);
        popup.querySelector('.create-client-cancel').addEventListener('click', hide);
        popup.querySelector('.create-client-submit').addEventListener('click', function() {
            var name = popup.querySelector('.create-client-name').value.trim();
            var email = popup.querySelector('.create-client-email').value.trim();
            var phone = popup.querySelector('.create-client-phone').value.trim();
            if (!name || !email) {
                showToast('Preencha nome e email.', 'error');
                return;
            }
            var btn = popup.querySelector('.create-client-submit');
            btn.disabled = true;
            btn.textContent = 'A criar...';
            fetch(agendaClientsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ name: name, email: email, phone: phone || null })
            })
            .then(function(r) { return r.json().then(function(data) {
                if (!r.ok) {
                    var msg = 'Erro ao criar cliente.';
                    if (data.errors && data.errors.email) msg = 'O email inserido já existe associado a um cliente.';
                    else if (data.message) msg = data.message;
                    throw new Error(msg);
                }
                return data;
            }); })
            .then(function(client) {
                hide();
                var c = { id: String(client.id), name: client.name || name, email: client.email || email, avatar_url: client.avatar_url || '' };
                if (context === 'novaMarcacao') {
                    novaMarcacaoSelectedClient = c;
                    document.getElementById('novaMarcacaoClientSelectedName').textContent = c.name;
                    document.getElementById('novaMarcacaoClientSelectedEmail').textContent = c.email || '—';
                    document.getElementById('novaMarcacaoClientAvatar').classList.add('d-none');
                    var initials = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                    document.getElementById('novaMarcacaoClientAvatarFallback').textContent = initials;
                    document.getElementById('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
                    document.getElementById('novaMarcacaoClientSelected').classList.remove('d-none');
                    document.getElementById('novaMarcacaoClientSearchWrap').classList.add('d-none');
                    document.getElementById('novaMarcacaoClientResults').innerHTML = '';
                    document.getElementById('novaMarcacaoClientSearch').value = '';
                    document.getElementById('novaMarcacaoClientCancelBtn').classList.add('d-none');
                    document.getElementById('novaMarcacaoCreateClientBtn').classList.add('d-none');
                    window._novaMarcacaoPreviousClient = null;
                } else {
                    eventDetailSelectedClient = c;
                    document.getElementById('eventDetailClientSelectedName').textContent = c.name;
                    document.getElementById('eventDetailClientSelectedEmail').textContent = c.email || '—';
                    if (c.avatar_url) {
                        document.getElementById('eventDetailClientAvatar').src = c.avatar_url;
                        document.getElementById('eventDetailClientAvatar').classList.remove('d-none');
                        document.getElementById('eventDetailClientAvatarFallback').classList.add('d-none');
                    } else {
                        document.getElementById('eventDetailClientAvatar').classList.add('d-none');
                        var inits = (c.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                        document.getElementById('eventDetailClientAvatarFallback').textContent = inits;
                        document.getElementById('eventDetailClientAvatarFallback').classList.remove('d-none');
                    }
                    document.getElementById('eventDetailClientSelected').classList.remove('d-none');
                    document.getElementById('eventDetailClientSearchWrap').classList.add('d-none');
                    document.getElementById('eventDetailClientResults').innerHTML = '';
                    document.getElementById('eventDetailClientSearch').value = '';
                    document.getElementById('eventDetailClientCancelBtn').classList.add('d-none');
                    window._eventDetailPreviousClient = null;
                }
                showToast('Cliente criado com sucesso.', 'success');
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Criar';
                showToast(err.message || 'Erro ao criar cliente.', 'error');
            });
        });
        requestAnimationFrame(function() {
            popup.classList.add('is-open');
            var btnEl = (evt && evt.target) ? evt.target.closest('button') : null;
            var rect = btnEl ? btnEl.getBoundingClientRect() : { left: 200, bottom: 200 };
            var offset = 8;
            var container = modalContent && modalContent.parentElement ? modalContent.parentElement.getBoundingClientRect() : null;
            var left, top;
            if (container) {
                left = (rect.left || 200) - container.left;
                top = (rect.bottom !== undefined ? rect.bottom : 200) + offset - container.top;
            } else {
                left = rect.left || 200;
                top = (rect.bottom !== undefined ? rect.bottom : 200) + offset;
            }
            var popupRect = popup.getBoundingClientRect();
            var vw = container ? container.width : window.innerWidth;
            var vh = container ? container.height : window.innerHeight;
            var maxLeft = vw - popupRect.width - offset;
            var maxTop = vh - popupRect.height - offset;
            left = Math.max(offset, Math.min(left, maxLeft));
            top = Math.max(offset, Math.min(top, maxTop));
            popup.style.left = left + 'px';
            popup.style.top = top + 'px';
            document.addEventListener('click', ch);
            document.addEventListener('keydown', eh);
            popup.querySelector('.create-client-name').focus();
        });
    }
    document.getElementById('novaMarcacaoCreateClientBtn').addEventListener('click', function(e) {
        e.preventDefault();
        openCreateClientQuickMenu('novaMarcacao', e);
    });
    document.getElementById('eventDetailCreateClientBtn').addEventListener('click', function(e) {
        e.preventDefault();
        openCreateClientQuickMenu('eventDetail', e);
    });

    document.getElementById('novaMarcacaoClientCancelBtn').addEventListener('click', function() {
        var prev = window._novaMarcacaoPreviousClient;
        if (prev) {
            novaMarcacaoSelectedClient = prev;
            document.getElementById('novaMarcacaoClientSelectedName').textContent = prev.name;
            document.getElementById('novaMarcacaoClientSelectedEmail').textContent = prev.email || '—';
            if (prev.avatar_url) {
                document.getElementById('novaMarcacaoClientAvatar').src = prev.avatar_url;
                document.getElementById('novaMarcacaoClientAvatar').classList.remove('d-none');
                document.getElementById('novaMarcacaoClientAvatarFallback').classList.add('d-none');
            } else {
                document.getElementById('novaMarcacaoClientAvatar').classList.add('d-none');
                var initials = (prev.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                document.getElementById('novaMarcacaoClientAvatarFallback').textContent = initials;
                document.getElementById('novaMarcacaoClientAvatarFallback').classList.remove('d-none');
            }
            document.getElementById('novaMarcacaoClientSelected').classList.remove('d-none');
            document.getElementById('novaMarcacaoClientSearchWrap').classList.add('d-none');
            document.getElementById('novaMarcacaoClientResults').innerHTML = '';
            document.getElementById('novaMarcacaoClientSearch').value = '';
            document.getElementById('novaMarcacaoCreateClientBtn').classList.add('d-none');
            this.classList.add('d-none');
            window._novaMarcacaoPreviousClient = null;
        }
    });

    document.getElementById('novaMarcacaoAddMoreServicesBtn').addEventListener('click', function() {
        document.getElementById('novaMarcacaoServiceSelected').classList.add('d-none');
        document.getElementById('novaMarcacaoServicesListCancelWrap').classList.remove('d-none');
        document.getElementById('novaMarcacaoServicesList').classList.remove('d-none');
    });

    document.getElementById('novaMarcacaoCancelAddServicesBtn').addEventListener('click', function() {
        document.getElementById('novaMarcacaoServicesList').classList.add('d-none');
        document.getElementById('novaMarcacaoServicesListCancelWrap').classList.add('d-none');
        document.getElementById('novaMarcacaoServiceSelected').classList.remove('d-none');
    });

    document.getElementById('novaMarcacaoObservacoesToggle').addEventListener('click', function() {
        var wrap = document.getElementById('novaMarcacaoObservacoesWrap');
        wrap.classList.toggle('show');
        this.classList.toggle('show');
    });

    document.getElementById('eventDetailObservacoesToggle').addEventListener('click', function() {
        var wrap = document.getElementById('eventDetailObservacoesWrap');
        wrap.classList.toggle('show');
        this.classList.toggle('show');
    });

    document.getElementById('eventDetailAddMoreServicesBtn').addEventListener('click', function() {
        document.getElementById('eventDetailServiceSelected').classList.add('d-none');
        document.getElementById('eventDetailServicesListCancelWrap').classList.remove('d-none');
        document.getElementById('eventDetailServicesList').classList.remove('d-none');
    });

    document.getElementById('eventDetailCancelAddServicesBtn').addEventListener('click', function() {
        document.getElementById('eventDetailServicesList').classList.add('d-none');
        document.getElementById('eventDetailServicesListCancelWrap').classList.add('d-none');
        document.getElementById('eventDetailServiceSelected').classList.remove('d-none');
    });

    document.getElementById('eventDetailClientClear').addEventListener('click', function() {
        var prev = eventDetailSelectedClient ? { id: eventDetailSelectedClient.id, name: eventDetailSelectedClient.name, email: eventDetailSelectedClient.email || '', avatar_url: eventDetailSelectedClient.avatar_url || '' } : null;
        eventDetailSelectedClient = null;
        document.getElementById('eventDetailClientSelected').classList.add('d-none');
        document.getElementById('eventDetailClientSearchWrap').classList.remove('d-none');
        document.getElementById('eventDetailClientResults').classList.remove('d-none');
        document.getElementById('eventDetailClientSearch').value = '';
        document.getElementById('eventDetailClientSearch').focus();
        document.getElementById('eventDetailClientResults').innerHTML = '';
        document.getElementById('eventDetailCreateClientBtn').classList.remove('d-none');
        if (prev) {
            window._eventDetailPreviousClient = prev;
            document.getElementById('eventDetailClientCancelBtn').classList.remove('d-none');
        }
    });
    document.getElementById('eventDetailClientCancelBtn').addEventListener('click', function() {
        var prev = window._eventDetailPreviousClient;
        if (prev) {
            eventDetailSelectedClient = prev;
            document.getElementById('eventDetailClientSelectedName').textContent = prev.name;
            document.getElementById('eventDetailClientSelectedEmail').textContent = prev.email || '—';
            if (prev.avatar_url) {
                document.getElementById('eventDetailClientAvatar').src = prev.avatar_url;
                document.getElementById('eventDetailClientAvatar').classList.remove('d-none');
                document.getElementById('eventDetailClientAvatarFallback').classList.add('d-none');
            } else {
                document.getElementById('eventDetailClientAvatar').classList.add('d-none');
                var initials = (prev.name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                document.getElementById('eventDetailClientAvatarFallback').textContent = initials;
                document.getElementById('eventDetailClientAvatarFallback').classList.remove('d-none');
            }
            document.getElementById('eventDetailClientSelected').classList.remove('d-none');
            document.getElementById('eventDetailClientSearchWrap').classList.add('d-none');
            document.getElementById('eventDetailClientResults').classList.add('d-none');
            document.getElementById('eventDetailClientSearch').value = '';
            document.getElementById('eventDetailClientResults').innerHTML = '';
            document.getElementById('eventDetailCreateClientBtn').classList.add('d-none');
            this.classList.add('d-none');
            window._eventDetailPreviousClient = null;
        }
    });

    document.getElementById('eventDetailClientSearch').addEventListener('input', (function() {
        var t;
        return function() {
            clearTimeout(t);
            var q = this.value.trim();
            if (q.length < 1) {
                document.getElementById('eventDetailClientResults').innerHTML = '';
                return;
            }
            t = setTimeout(function() {
                document.getElementById('eventDetailClientResults').innerHTML = '<div class="text-muted small">A pesquisar...</div>';
                fetch(agendaClientsUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(clients) {
                        if (!clients.length) {
                            document.getElementById('eventDetailClientResults').innerHTML = '<div class="text-muted small">Nenhum cliente encontrado.</div>';
                            return;
                        }
                        var html = clients.map(function(c) {
                            var dataAttrs = 'data-id="' + c.id + '" data-name="' + (c.name || '').replace(/"/g, '&quot;') + '" data-email="' + (c.email || '').replace(/"/g, '&quot;') + '" data-avatar="' + (c.avatar_url || '').replace(/"/g, '&quot;') + '"';
                            return '<div class="nova-marcacao-client-item event-detail-client-item" ' + dataAttrs + '>' + (c.name || '') + (c.email ? ' <small class="text-muted">' + c.email + '</small>' : '') + '</div>';
                        }).join('');
                        document.getElementById('eventDetailClientResults').innerHTML = html;
                        document.getElementById('eventDetailClientResults').querySelectorAll('.event-detail-client-item').forEach(function(el) {
                            el.addEventListener('click', function() {
                                var name = this.dataset.name || '', email = this.dataset.email || '', avatarUrl = this.dataset.avatar || '';
                                eventDetailSelectedClient = { id: this.dataset.id, name: name, email: email, avatar_url: avatarUrl };
                                document.getElementById('eventDetailClientSelectedName').textContent = name;
                                document.getElementById('eventDetailClientSelectedEmail').textContent = email || '—';
                                if (avatarUrl) {
                                    document.getElementById('eventDetailClientAvatar').src = avatarUrl;
                                    document.getElementById('eventDetailClientAvatar').classList.remove('d-none');
                                    document.getElementById('eventDetailClientAvatarFallback').classList.add('d-none');
                                } else {
                                    document.getElementById('eventDetailClientAvatar').classList.add('d-none');
                                    var initials = (name || '?').split(' ').map(function(w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase() || '?';
                                    document.getElementById('eventDetailClientAvatarFallback').textContent = initials;
                                    document.getElementById('eventDetailClientAvatarFallback').classList.remove('d-none');
                                }
                                document.getElementById('eventDetailClientSelected').classList.remove('d-none');
                                document.getElementById('eventDetailClientSearchWrap').classList.add('d-none');
                                document.getElementById('eventDetailClientResults').innerHTML = '';
                                document.getElementById('eventDetailClientSearch').value = '';
                                document.getElementById('eventDetailClientCancelBtn').classList.add('d-none');
                                document.getElementById('eventDetailCreateClientBtn').classList.add('d-none');
                                window._eventDetailPreviousClient = null;
                            });
                        });
                    });
            }, 300);
        };
    })());

    document.getElementById('eventDetailEditForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var id = document.getElementById('eventDetailEditId').value;
        var title = eventDetailCurrentData?.title || '';
        if (eventDetailCurrentData?.event_type === 'marcacao' && eventDetailSelectedServices.length > 0) {
            var clientName = (eventDetailSelectedClient && eventDetailSelectedClient.name) || eventDetailCurrentData.client_name || '';
            var serviceNames = eventDetailSelectedServices.map(function(s) { return s.name; }).join(', ');
            title = (clientName || 'Cliente') + ' - ' + serviceNames;
        }
        var totalDur = eventDetailSelectedServices.reduce(function(sum, s) {
            var d = (s.duration || 0) + (s.extras || []).reduce(function(s2, e) { return s2 + (e.duration || 0); }, 0);
            return sum + d;
        }, 0);
        var startStr = document.getElementById('eventDetailEditStart').value;
        var endStr = startStr;
        if (totalDur > 0 && startStr) {
            var start = new Date(startStr);
            var end = new Date(start.getTime() + totalDur * 60 * 1000);
            endStr = end.getFullYear() + '-' + String(end.getMonth() + 1).padStart(2, '0') + '-' + String(end.getDate()).padStart(2, '0') + 'T' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
        }
        var payload = {
            title: title,
            start_at: startStr,
            end_at: endStr,
            description: document.getElementById('eventDetailObservacoes').value,
            status: document.getElementById('eventDetailStatus').value
        };
        if (payload.status === 'cancelado') {
            payload.cancellation_reason = document.getElementById('eventDetailCancelReason').value;
        }
        if (eventDetailCurrentData?.event_type === 'marcacao') {
            payload.client_id = eventDetailSelectedClient ? eventDetailSelectedClient.id : null;
            payload.services = eventDetailSelectedServices.map(function(s) {
                return {
                    service_id: s.service_id,
                    duration: s.duration,
                    price: s.price,
                    original_price: s.original_price != null ? s.original_price : s.price,
                    extras: (s.extras || []).map(function(e) { return { extra_id: e.id, duration: e.duration || 0, price: e.price || 0 }; })
                };
            });
        }
        var btn = document.getElementById('eventDetailSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A guardar...';
        fetch('{{ url("agenda/events") }}/' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            return r.json().then(function(res) {
                if (!r.ok) {
                    var msg = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : null) || 'Erro ao guardar.';
                    throw new Error(msg);
                }
                return res;
            });
        })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
            if (res.success && res.event) {
                eventDetailWasSaved = true;
                var ev = calendar.getEventById(id);
                if (ev) {
                    ev.setProp('title', res.event.title);
                    ev.setStart(res.event.start);
                    ev.setEnd(res.event.end);
                    var ep = res.event.extendedProps || {};
                    Object.keys(ep).forEach(function(k) { ev.setExtendedProp(k, ep[k]); });
                }
                bootstrap.Modal.getInstance(document.getElementById('eventDetailEditModal')).hide();
            } else {
                showToast(res.message || 'Erro ao guardar.', 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
            var msg = (err && err.message && err.message.indexOf('Unexpected') === -1) ? err.message : 'Erro de ligação. Verifique os logs do servidor se o problema persistir.';
            showToast(msg, 'error');
        });
    });

    document.getElementById('novaMarcacaoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!novaMarcacaoSelectedServices.length) {
            showToast('Selecione pelo menos um serviço.', 'error');
            return;
        }
        if (!novaMarcacaoSelectedClient || !novaMarcacaoSelectedClient.name) {
            showToast('Selecione um cliente.', 'error');
            return;
        }
        var serviceNames = novaMarcacaoSelectedServices.map(function(s) { return s.name; }).join(', ');
        var title = novaMarcacaoSelectedClient.name + ' - ' + serviceNames;
        var btn = document.getElementById('novaMarcacaoSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>A guardar...';
        var servicesPayload = novaMarcacaoSelectedServices.map(function(s) {
            return {
                service_id: s.service_id,
                duration: s.duration,
                price: s.price,
                original_price: s.original_price != null ? s.original_price : s.price,
                extras: (s.extras || []).map(function(e) { return { extra_id: e.id, duration: e.duration || 0, price: e.price || 0 }; })
            };
        });
        var payload = {
            title: title,
            start_at: document.getElementById('novaMarcacaoStart').value,
            end_at: document.getElementById('novaMarcacaoEnd').value,
            description: document.getElementById('novaMarcacaoObservacoes').value,
            event_type: 'marcacao',
            user_id: document.getElementById('novaMarcacaoAgentId').value,
            client_id: novaMarcacaoSelectedClient ? novaMarcacaoSelectedClient.id : null,
            services: servicesPayload
        };
        var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch('{{ url("agenda/events") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            return r.json().then(function(res) {
                if (!r.ok) {
                    var msg = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : null) || 'Erro ao criar marcação.';
                    throw new Error(msg);
                }
                return res;
            });
        })
        .then(function(res) {
            btn.disabled = false;
            btn.textContent = 'Criar marcação';
            if (res.success && res.event) {
                if (currentViewMode === 'consultant' && res.event.extendedProps?.user_id) {
                    res.event.resourceId = String(res.event.extendedProps.user_id);
                }
                calendar.addEvent(res.event);
                bootstrap.Modal.getInstance(document.getElementById('novaMarcacaoModal')).hide();
            } else {
                showToast(res.message || 'Erro ao criar marcação.', 'error');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = 'Criar marcação';
            var msg = (err && err.message && err.message.indexOf('Unexpected') === -1) ? err.message : 'Erro de ligação. Verifique os logs do servidor se o problema persistir.';
            showToast(msg, 'error');
        });
    });

    document.getElementById('novaMarcacaoModal').addEventListener('hidden.bs.modal', function() {
        novaMarcacaoSelectedClient = null;
        novaMarcacaoServicesData = null;
        novaMarcacaoSelectedServices = [];
        document.getElementById('novaMarcacaoTotalPrice').textContent = '0,00 €';
        window._novaMarcacaoPreviousClient = null;
        document.getElementById('novaMarcacaoClientCancelBtn').classList.add('d-none');
        document.getElementById('novaMarcacaoClientSearchWrap').classList.remove('d-none');
        document.getElementById('novaMarcacaoClientSelected').classList.add('d-none');
    });

    function toggleEventServiceBlock() {
        var type = document.getElementById('eventType').value;
        var wrap = document.getElementById('eventServiceWrap');
        var sel = document.getElementById('eventService');
        if (type === 'marcacao') {
            wrap.classList.remove('d-none');
            var memberId = document.getElementById('eventUser').value || '{{ auth()->id() }}';
            loadMemberServices(memberId, null);
        } else {
            wrap.classList.add('d-none');
            sel.innerHTML = '<option value="">Selecione o membro primeiro</option>';
        }
    }

    function loadMemberServices(userId, thenSelectServiceId) {
        var sel = document.getElementById('eventService');
        sel.innerHTML = '<option value="">A carregar...</option>';
        if (!userId) {
            sel.innerHTML = '<option value="">Selecione o membro primeiro</option>';
            return;
        }
        fetch(agendaMembersServicesUrl + '/' + userId + '/services', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                sel.innerHTML = '<option value="">— Selecionar serviço —</option>';
                (data.categories || []).forEach(function(cat) {
                    var optgroup = document.createElement('optgroup');
                    optgroup.label = cat.name || 'Outros';
                    (cat.services || []).forEach(function(s) {
                        var opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        optgroup.appendChild(opt);
                    });
                    sel.appendChild(optgroup);
                });
                if (thenSelectServiceId) {
                    sel.value = String(thenSelectServiceId);
                }
            })
            .catch(function() {
                sel.innerHTML = '<option value="">Erro ao carregar serviços</option>';
            });
    }

    document.getElementById('eventType').addEventListener('change', toggleEventServiceBlock);
    document.getElementById('eventUser').addEventListener('change', function() {
        if (document.getElementById('eventType').value === 'marcacao') {
            var memberId = this.value || '{{ auth()->id() }}';
            loadMemberServices(memberId, null);
        }
    });

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'resourceTimeGridDay',
        locale: 'pt',
        editable: true,
        customButtons: {
            currentDate: {
                text: '',
                click: function() {
                    // Botão apenas informativo, não faz nada
                }
            },
            newEvent: {
                text: 'Novo evento',
                click: function() {
                    openCreateEventModal();
                }
            },
            consultantFilter: {
                text: 'Toda a equipa',
                click: function() {
                    // Dropdown será inicializado após render
                }
            },
            viewSelector: {
                text: 'Mês',
                click: function() {
                    // Dropdown será inicializado após render
                }
            }
        },
        headerToolbar: {
            left: 'today prev currentDate next',
            center: 'title',
            right: 'consultantFilter viewSelector newEvent'
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            resourceTimeGridDay: 'Por consultor',
            prev: '',
            next: ''
        },
        slotMinTime: '00:00:00',
        slotMaxTime: '23:59:00',
        slotDuration: '00:15:00',
        slotLabelInterval: '01:00',
        allDaySlot: false,
        nowIndicator: true,
        scrollTime: new Date().toTimeString().slice(0, 5) + ':00',
        scrollTimeReset: false,
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        slotLaneDidMount: function(arg) {
            if (arg.el && arg.date) arg.el.setAttribute('data-slot-date', arg.date.toISOString());
        },
        dayMaxEvents: 2,
        dayMaxEventRows: 2,
        eventContent: function(arg) {
            const start = arg.event.start;
            const end = arg.event.end;
            const fmt = function(d) { return d ? (String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')) : ''; };
            const startStr = fmt(start);
            const endStr = fmt(end);
            const timeStr = (startStr && endStr) ? (startStr + ' - ' + endStr) : (startStr || '');
            const timeHtml = (startStr && endStr)
                ? ('<span class="fc-event-time-start">' + startStr + '</span><span class="fc-event-time-range"> - ' + endStr + '</span>')
                : ('<span class="fc-event-time-start">' + (startStr || '') + '</span>');
            const extProps = arg.event.extendedProps || {};
            const statusIcon = extProps.status_icon || null;
            const clientName = (extProps.client_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const serviceName = (extProps.service_name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const fallbackTitle = (arg.event.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            let iconHtml = '';
            if (statusIcon) {
                iconHtml = '<i class="' + statusIcon + ' fc-event-status-icon"></i>';
            }
            let contentHtml = '';
            if (clientName || serviceName) {
                contentHtml = '<span class="fc-event-time">' + timeHtml + '</span> <strong class="fc-event-client">' + (clientName || '—') + '</strong>';
                if (serviceName) {
                    contentHtml += '<span class="fc-event-service-line">' + serviceName + '</span>';
                }
            } else {
                contentHtml = '<span class="fc-event-time">' + timeHtml + '</span> <span class="fc-event-title">' + fallbackTitle + '</span>';
            }
            return { html: '<div class="fc-event-content-wrapper">' + contentHtml + iconHtml + '</div>' };
        },
        dayHeaderFormat: function(arg) {
            const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            let d = null;
            
            // FullCalendar v6 passa um objeto Date Formatter customizado
            // O objeto date tem propriedades: marker (ISO string), year, month, day, etc.
            if (arg && arg.date) {
                const dateObj = arg.date;
                
                // Tentar usar marker (ISO string) primeiro
                if (dateObj.marker) {
                    d = new Date(dateObj.marker);
                }
                // Se não tem marker, construir a data a partir das propriedades
                else if (dateObj.year !== undefined && dateObj.month !== undefined && dateObj.day !== undefined) {
                    // month é 0-indexed no FullCalendar, mas new Date espera 0-indexed também
                    d = new Date(dateObj.year, dateObj.month, dateObj.day);
                }
                // Fallback: tentar usar como Date se for
                else if (dateObj instanceof Date) {
                    d = dateObj;
                }
            }
            
            // Se não conseguiu obter a data válida
            if (!d || isNaN(d.getTime())) {
                return '';
            }
            
            const dayIndex = d.getDay();
            
            // Verificar qual é a vista atual
            const currentView = calendar ? calendar.view.type : '';
            
            // Na vista de mês, mostrar apenas o nome do dia
            if (currentView === 'dayGridMonth') {
                if (dayIndex >= 0 && dayIndex <= 6) {
                    return days[dayIndex];
                }
                return '';
            }
            
            // Nas outras vistas (semana, dia), mostrar nome + número
            const dayNumber = d.getDate();
            if (dayIndex >= 0 && dayIndex <= 6 && dayNumber >= 1 && dayNumber <= 31) {
                return days[dayIndex] + ' ' + dayNumber;
            }
            
            return '';
        },
        /* === 2) Clique numa célula: mostrar menu rápido; "Criar evento" abre o modal === */
        dateClick: function(info) {
            function toLocalDateTimeStr(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const h = String(d.getHours()).padStart(2, '0');
                const min = String(d.getMinutes()).padStart(2, '0');
                return y + '-' + m + '-' + day + 'T' + h + ':' + min;
            }
            var startDate = info.date;
            var endDate;
            if (info.allDay) {
                endDate = new Date(startDate);
                endDate.setHours(endDate.getHours() + 1);
            } else {
                endDate = new Date(startDate.getTime() + 60 * 60 * 1000);
            }
            var resourceId = info.resource ? info.resource.id : null;
            var startStr = toLocalDateTimeStr(startDate);
            var endStr = toLocalDateTimeStr(endDate);

            var daysPt = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            var monthsPt = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            var d = startDate;
            var headingLabel = daysPt[d.getDay()] + ', ' + d.getDate() + ' ' + monthsPt[d.getMonth()] + ' · ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            var timeLabel = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');

            clearAgendaCellHighlight();
            var target = info.jsEvent.target;
            var slotTd = target.closest('td');
            if (slotTd && (slotTd.closest('.fc-timegrid-axis') || slotTd.classList.contains('fc-timegrid-slot-label'))) slotTd = null;
            if (slotTd && (resourceId || info.jsEvent.clientX != null)) {
                var wrapper = createCellHighlightForColumn(slotTd, resourceId, timeLabel, info.jsEvent.clientX);
                if (wrapper) _agendaHighlight.wrapper = wrapper;
            }
            if (!_agendaHighlight.wrapper && slotTd) {
                var dayCell = target.closest('.fc-daygrid-day');
                if (dayCell) {
                    dayCell.classList.add('agenda-cell-highlighted');
                    _agendaHighlight.wrapper = { remove: function() {}, _isDayGrid: true };
                    _agendaHighlight.wrapper._parent = dayCell;
                } else {
                    var slotRect = slotTd.getBoundingClientRect();
                    var wrapper = document.createElement('div');
                    wrapper.className = 'agenda-cell-highlight agenda-cell-highlight-active';
                    wrapper.style.position = 'fixed';
                    wrapper.style.top = slotRect.top + 'px';
                    wrapper.style.left = slotRect.left + 'px';
                    wrapper.style.width = slotRect.width + 'px';
                    wrapper.style.height = slotRect.height + 'px';
                    wrapper.style.zIndex = '9998';
                    wrapper.style.pointerEvents = 'none';
                    var span = document.createElement('span');
                    span.className = 'agenda-cell-time-overlay';
                    span.textContent = timeLabel;
                    wrapper.appendChild(span);
                    document.body.appendChild(wrapper);
                    _agendaHighlight.wrapper = wrapper;
                    _agendaHighlight.wrapper._isFullRow = true;
                }
            }

            var options = [
                {
                    label: 'Nova marcação',
                    icon: 'bi bi-calendar-check',
                    iconColor: 'var(--accent-color, #0d6efd)',
                    action: function() {
                        openNovaMarcacaoModal(startStr, endStr, resourceId);
                    }
                },
                {
                    label: 'Novo tempo pessoal',
                    icon: 'bi bi-person',
                    iconColor: 'var(--bs-secondary, #6c757d)',
                    action: function() {
                        openCreateEventModal(startStr, endStr, resourceId, 'tempo_pessoal');
                    }
                }
            ];
            clearAgendaHoverHighlight();
            showQuickMenu(info.jsEvent.clientX, info.jsEvent.clientY, headingLabel, options);
        },
        resources: function(fetchInfo, successCallback, failureCallback) {
            fetch(resourcesUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(function(res) {
                allResources = res;
                let out = res;
                if (consultantFilterIds.length > 0) {
                    out = res.filter(function(r) { return consultantFilterIds.indexOf(r.id) !== -1; });
                }
                successCallback(out);
                
                // Atualizar dropdown de consultores após recursos serem carregados
                if (currentViewMode === 'consultant') {
                    setTimeout(function() {
                        initConsultantDropdown();
                        updateConsultantFilterButton();
                    }, 50);
                }
            })
            .catch(failureCallback);
        },
        resourceLabelContent: function(arg) {
            const res = arg.resource;
            const title = (res.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const ext = res.extendedProps || {};
            const avatarUrl = ext.avatarUrl || '';
            if (!avatarUrl) {
                return { html: '<span class="fc-resource-consultant-name">' + title + '</span>' };
            }
            return {
                html: '<span class="fc-resource-consultant-label">' +
                    '<img class="fc-resource-consultant-avatar" src="' + String(avatarUrl).replace(/"/g, '&quot;') + '" alt="" />' +
                    '<span class="fc-resource-consultant-name">' + title + '</span></span>'
            };
        },
        events: function(info, successCallback, failureCallback) {
            const params = new URLSearchParams({
                start: info.startStr,
                end: info.endStr
            });
            if (currentViewMode === 'consultant') params.set('for_resources', '1');
            fetch(eventsUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(successCallback)
            .catch(failureCallback);
        },
        eventDidMount: function(info) {
            info.el.style.setProperty('color', '#000', 'important');
            if (info.event.backgroundColor) {
                info.el.style.setProperty('background-color', info.event.backgroundColor, 'important');
            }
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const id = info.event.id;
            if (eventDetailModalLoading) {
                return;
            }
            eventDetailModalLoading = true;
            fetch('{{ url('agenda/events') }}/' + id, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(function(data) {
                populateEventDetailEditModal(data);
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('eventDetailEditModal'));
                modal.show();
                eventDetailModalLoading = false;
            })
            .catch(function(error) {
                console.error('Erro ao carregar detalhes do evento:', error);
                showToast('Erro ao carregar detalhes do evento.', 'error');
                eventDetailModalLoading = false;
            });
        },
        eventDrop: function(info) {
            const timeEditable = info.event.extendedProps.is_time_editable !== false;
            const reassignOnly = info.newResource && currentViewMode === 'consultant';
            if (!timeEditable && !reassignOnly) {
                info.revert();
                return;
            }
            const id = info.event.id;
            const start = info.event.start.toISOString();
            const end = info.event.end ? info.event.end.toISOString() : start;
            const payload = { start_at: start, end_at: end };
            if (info.newResource && currentViewMode === 'consultant') {
                payload.user_id = info.newResource.id || null;
            }
            const url = '{{ url('agenda/events') }}/' + id + '/update';
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) {
                if (!r.ok) throw new Error(r.statusText);
                return r.json();
            })
            .then(function(res) {
                if (!res.success) {
                    showToast(res.message || 'Erro ao atualizar.', 'error');
                    info.revert();
                    return;
                }
                if (res.event && payload.user_id !== undefined) {
                    info.event.setExtendedProp('user_id', payload.user_id);
                    if (res.event.extendedProps && res.event.extendedProps.user_name) info.event.setExtendedProp('user_name', res.event.extendedProps.user_name);
                }
            })
            .catch(function(err) {
                console.error('eventDrop error', err);
                info.revert();
            });
        },
        eventResize: function(info) {
            if (info.event.extendedProps.is_time_editable === false) { info.revert(); return; }
            const id = info.event.id;
            const start = info.event.start.toISOString();
            const end = info.event.end ? info.event.end.toISOString() : start;
            const url = '{{ url('agenda/events') }}/' + id + '/update';
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ start_at: start, end_at: end })
            })
            .then(function(r) {
                if (!r.ok) throw new Error(r.statusText);
                return r.json();
            })
            .then(function(res) {
                if (!res.success) {
                    showToast(res.message || 'Erro ao atualizar.', 'error');
                    info.revert();
                }
            })
            .catch(function(err) {
                console.error('eventResize error', err);
                info.revert();
            });
        },
        datesSet: function(info) {
            if ((info.view.type.includes('timeGrid') || info.view.type.includes('resourceTimeGrid')) &&
                calendar.view.activeStart <= new Date() && calendar.view.activeEnd >= new Date()) {
                setTimeout(function() {
                    const now = new Date();
                    const currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    calendar.scrollToTime(currentTime);
                }, 50);
            }
            requestAnimationFrame(function() {
                const viewSelectorBtn = calendarEl.querySelector('.fc-viewSelector-button');
                const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
                const currentDateBtn = calendarEl.querySelector('.fc-currentDate-button');
                const prevBtn = calendarEl.querySelector('.fc-prev-button');
                const nextBtn = calendarEl.querySelector('.fc-next-button');
                if (viewSelectorBtn && (!viewSelectorBtn.closest('.dropdown') || !calendarEl.querySelector('#viewSelectorDropdown'))) {
                    viewSelectorBtn.dataset.initialized = '';
                    initViewSelectorDropdown();
                }
                if (consultantBtn && currentViewMode === 'consultant' && (!consultantBtn.closest('.dropdown') || !calendarEl.querySelector('#consultantDropdown'))) {
                    consultantBtn.dataset.initialized = '';
                    initConsultantDropdown();
                }
                var viewType = calendar.view.type;
                var startDate = viewType === 'dayGridMonth' ? calendar.view.currentStart : info.start;
                if (currentDateBtn) {
                    currentDateBtn.textContent = formatCurrentDateButton(viewType, startDate, info.end);
                    currentDateBtn.style.pointerEvents = 'none';
                    currentDateBtn.style.cursor = 'default';
                    currentDateBtn.style.fontWeight = '500';
                    currentDateBtn.style.color = '#212529';
                    currentDateBtn.style.opacity = '1';
                }
                if (viewSelectorBtn && viewSelectorBtn.dataset.initialized === '1') {
                    var viewLabels = { dayGridMonth: 'Mês', timeGridWeek: 'Semana', resourceTimeGridDay: 'Por consultor' };
                    viewSelectorBtn.textContent = viewLabels[viewType] || 'Semana';
                }
                if (consultantBtn && consultantBtn.dataset.initialized === '1' && currentViewMode === 'consultant') {
                    var res = selectedConsultantId && allResources.length ? allResources.find(function(r) { return r.id === selectedConsultantId; }) : null;
                    consultantBtn.textContent = res ? res.title : 'Toda a equipa';
                }
                if (prevBtn) prevBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-left"></span>';
                if (nextBtn) nextBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-right"></span>';
                applyToolbarStyles();
            });
        },
        viewDidMount: function(info) {
            const isConsultant = info.view.type === 'resourceTimeGridDay';
            currentViewMode = isConsultant ? 'consultant' : 'normal';
            
            // Fazer scroll para a hora atual se for uma vista de tempo
            if (info.view.type.includes('timeGrid') || info.view.type.includes('resourceTimeGrid')) {
                setTimeout(function() {
                    const now = new Date();
                    const currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
                    calendar.scrollToTime(currentTime);
                }, 100);
            }
            
            requestAnimationFrame(function() { applyToolbarStyles(); });
            setTimeout(function() {
                initViewSelectorDropdown();
                updateViewSelectorButton(info.view.type);
                updateViewDropdownActive(info.view.type);
                applyToolbarStyles();
            }, 0);
            
            // Mostrar/esconder botão de filtro de consultor
            const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
            if (consultantBtn) {
                consultantBtn.style.display = isConsultant ? 'inline-block' : 'none';
                consultantBtn.parentElement.style.display = isConsultant ? 'inline-block' : 'none';
            }
            
            if (isConsultant) {
                calendar.refetchResources();
                calendar.refetchEvents();
                setTimeout(function() {
                    initConsultantDropdown();
                    updateConsultantFilterButton();
                    applyToolbarStyles();
                }, 150);
            }
        }
    });
    calendar.render();

    /* TEST: overlay fora da tabela para evitar que as horas “mexam” na vertical; reverter este bloco se não correr bem */
    (function setupSlotHoverHighlight() {
        var calendarEl = document.getElementById('calendar');

        function updateHoverOverlay(e) {
            var target = e.target;
            if (!calendarEl.contains(target)) return;
            if (document.getElementById('agendaQuickMenu').classList.contains('is-open')) return;
            var slotEl = target.closest('[data-slot-date]');
            if (!slotEl) {
                clearAgendaHoverHighlight();
                return;
            }
            var slotTd = slotEl.closest('td');
            if (!slotTd) {
                clearAgendaHoverHighlight();
                return;
            }
            var dateStr = slotEl.getAttribute('data-slot-date');
            if (!dateStr) { clearAgendaHoverHighlight(); return; }
            var d = new Date(dateStr);
            var timeLabel = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            var clientX = e.clientX;
            var colEl = null;
            var cols = document.querySelectorAll('.fc-timegrid-col');
            for (var i = 0; i < cols.length; i++) {
                var r = cols[i].getBoundingClientRect();
                if (clientX >= r.left && clientX <= r.right) { colEl = cols[i]; break; }
            }
            if (!colEl) { clearAgendaHoverHighlight(); return; }

            var slotRect = slotTd.getBoundingClientRect();
            var colRect = colEl.getBoundingClientRect();
            if (colRect.width <= 0 || slotRect.height <= 0) return;

            /* Overlay em position:fixed e coordenadas viewport para ficar exactamente sobre a célula e visível (z-index alto) */
            if (!_agendaHoverHighlight) {
                var wrapper = document.createElement('div');
                wrapper.className = 'agenda-cell-highlight-hover';
                wrapper.setAttribute('role', 'presentation');
                wrapper.style.position = 'fixed';
                wrapper.style.zIndex = '9999';
                wrapper.style.pointerEvents = 'none';
                var timeSpan = document.createElement('span');
                timeSpan.className = 'agenda-cell-time-overlay';
                wrapper.appendChild(timeSpan);
                _agendaHoverHighlight = wrapper;
            }
            _agendaHoverHighlight.style.top = slotRect.top + 'px';
            _agendaHoverHighlight.style.left = colRect.left + 'px';
            _agendaHoverHighlight.style.width = colRect.width + 'px';
            _agendaHoverHighlight.style.height = slotRect.height + 'px';
            _agendaHoverHighlight.querySelector('.agenda-cell-time-overlay').textContent = timeLabel;
            if (!_agendaHoverHighlight.parentNode) document.body.appendChild(_agendaHoverHighlight);
        }
        function clearOnLeave(e) {
            if (!calendarEl.contains(e.relatedTarget)) clearAgendaHoverHighlight();
        }
        calendarEl.addEventListener('mousemove', updateHoverOverlay, { passive: true });
        calendarEl.addEventListener('mouseleave', clearOnLeave);
        /* Ao fazer scroll na grelha, remover o overlay para evitar desalinhamento; volta no próximo mousemove */
        calendarEl.addEventListener('scroll', function() {
            if (_agendaHoverHighlight) clearAgendaHoverHighlight();
        }, true);
    })();

    // Fazer scroll para a hora atual após render inicial
    setTimeout(function() {
        const now = new Date();
        const currentTime = now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0') + ':00';
        if (calendar.view.type.includes('timeGrid') || calendar.view.type.includes('resourceTimeGrid')) {
            calendar.scrollToTime(currentTime);
        }
    }, 200);
    
    
    // Função para atualizar o texto do botão de seleção de vista
    function updateViewSelectorButton(viewType) {
        const viewBtn = calendarEl.querySelector('.fc-viewSelector-button');
        if (!viewBtn) return;
        
        const viewLabels = {
            'dayGridMonth': 'Mês',
            'timeGridWeek': 'Semana',
            'resourceTimeGridDay': 'Por consultor'
        };
        
        viewBtn.textContent = viewLabels[viewType] || 'Mês';
    }

    // Inicializar dropdown de vistas
    function initViewSelectorDropdown() {
        const viewBtn = calendarEl.querySelector('.fc-viewSelector-button');
        if (!viewBtn) return;
        
        // Se já foi inicializado, verificar se a estrutura ainda existe
        if (viewBtn.dataset.initialized === '1') {
            const wrapper = viewBtn.closest('.dropdown');
            const dropdown = wrapper ? wrapper.querySelector('#viewSelectorDropdown') : null;
            if (wrapper && dropdown) {
                // Estrutura existe, apenas garantir estilos com important
                wrapper.style.setProperty('margin', '0', 'important');
                wrapper.style.setProperty('margin-left', '0', 'important');
                wrapper.style.setProperty('margin-right', '0', 'important');
                wrapper.style.setProperty('padding', '0', 'important');
                wrapper.style.setProperty('display', 'inline-block', 'important');
                viewBtn.style.setProperty('margin', '0', 'important');
                viewBtn.style.setProperty('margin-left', '0', 'important');
                viewBtn.style.setProperty('margin-right', '0', 'important');
                return;
            }
            // Estrutura perdida, re-inicializar
            viewBtn.dataset.initialized = '';
        }
        
        if (viewBtn.dataset.initialized) return;
        
        viewBtn.dataset.initialized = '1';
        
        // Garantir que temos um wrapper próprio para este dropdown
        let btnParent = viewBtn.parentElement;
        
        // Se o parent já tem outro dropdown ou não é um dropdown isolado, criar wrapper
        if (btnParent.querySelector('#consultantDropdown') || !btnParent.classList.contains('dropdown') || btnParent.querySelector('.fc-consultantFilter-button')) {
            // Criar um wrapper específico para o view selector
            const wrapper = document.createElement('div');
            wrapper.className = 'dropdown';
            wrapper.style.setProperty('display', 'inline-block', 'important');
            wrapper.style.setProperty('margin', '0', 'important');
            wrapper.style.setProperty('margin-left', '0', 'important');
            wrapper.style.setProperty('margin-right', '0', 'important');
            wrapper.style.setProperty('padding', '0', 'important');
            viewBtn.parentElement.insertBefore(wrapper, viewBtn);
            wrapper.appendChild(viewBtn);
            btnParent = wrapper;
        } else {
            btnParent.classList.add('dropdown');
            btnParent.style.setProperty('margin', '0', 'important');
            btnParent.style.setProperty('margin-left', '0', 'important');
            btnParent.style.setProperty('margin-right', '0', 'important');
            btnParent.style.setProperty('padding', '0', 'important');
            btnParent.style.setProperty('display', 'inline-block', 'important');
        }
        
        viewBtn.classList.add('dropdown-toggle');
        viewBtn.setAttribute('data-bs-toggle', 'dropdown');
        viewBtn.setAttribute('aria-expanded', 'false');
        viewBtn.setAttribute('id', 'viewSelectorBtn');
        viewBtn.style.setProperty('margin', '0', 'important');
        viewBtn.style.setProperty('margin-left', '0', 'important');
        viewBtn.style.setProperty('margin-right', '0', 'important');
        
        // Remover dropdown existente se houver (mas não o consultantDropdown)
        const existingDropdown = btnParent.querySelector('#viewSelectorDropdown');
        if (existingDropdown) {
            existingDropdown.remove();
        }
        
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown-menu';
        dropdown.id = 'viewSelectorDropdown';
        dropdown.setAttribute('aria-labelledby', 'viewSelectorBtn');
        
        const views = [
            { type: 'dayGridMonth', label: 'Mês' },
            { type: 'timeGridWeek', label: 'Semana' },
            { type: 'resourceTimeGridDay', label: 'Por consultor' }
        ];
        
        views.forEach(function(view) {
            const option = document.createElement('a');
            option.className = 'dropdown-item';
            option.href = '#';
            option.textContent = view.label;
            option.dataset.viewType = view.type;
            option.addEventListener('click', function(e) {
                e.preventDefault();
                const viewBtn = calendarEl.querySelector('.fc-viewSelector-button');
                if (viewBtn) {
                    viewBtn.textContent = '';
                    viewBtn.textContent = view.label;
                }
                calendar.changeView(view.type);
                calendar.gotoDate(new Date());
                updateViewSelectorButton(view.type);
                updateViewDropdownActive(view.type);
            });
            dropdown.appendChild(option);
        });
        
        btnParent.appendChild(dropdown);
        
        // Atualizar estado inicial
        updateViewSelectorButton(calendar.view.type);
        updateViewDropdownActive(calendar.view.type);
    }

    function updateViewDropdownActive(viewType) {
        const dropdown = calendarEl.querySelector('#viewSelectorDropdown');
        if (!dropdown) return;
        dropdown.querySelectorAll('.dropdown-item').forEach(function(item) {
            item.classList.remove('active');
            if (item.dataset.viewType === viewType) {
                item.classList.add('active');
            }
        });
    }
    
    /** Aplica margens/estilos da toolbar uma única vez (botões, dropdowns, chunks). */
    function applyToolbarStyles() {
        var sel = '.fc-button, .fc-viewSelector-button, .fc-consultantFilter-button, .fc-newEvent-button, .fc-today-button, .fc-prev-button, .fc-next-button, .fc-currentDate-button';
        calendarEl.querySelectorAll(sel).forEach(function(btn) {
            btn.style.setProperty('margin', '0', 'important');
        });
        calendarEl.querySelectorAll('.fc-toolbar-chunk .dropdown, .fc-header-toolbar .dropdown').forEach(function(d) {
            d.style.setProperty('margin', '0', 'important');
            d.style.setProperty('padding', '0', 'important');
        });
        var chunks = calendarEl.querySelectorAll('.fc-toolbar-chunk');
        chunks.forEach(function(chunk, i) {
            chunk.style.setProperty('margin', '0', 'important');
            chunk.style.setProperty('padding', '0', 'important');
            chunk.style.setProperty('gap', i === 0 ? '0.5rem' : '0', 'important');
        });
        var todayBtn = calendarEl.querySelector('.fc-today-button');
        if (todayBtn) {
            todayBtn.style.setProperty('background-color', '#ffffff', 'important');
            todayBtn.style.setProperty('border-color', '#dee2e6', 'important');
            todayBtn.style.setProperty('border-width', '1px', 'important');
            todayBtn.style.setProperty('border-style', 'solid', 'important');
            todayBtn.style.setProperty('color', '#212529', 'important');
            todayBtn.style.setProperty('font-weight', '400', 'important');
        }
        [calendarEl.querySelector('.fc-prev-button'), calendarEl.querySelector('.fc-next-button'), calendarEl.querySelector('.fc-viewSelector-button'), calendarEl.querySelector('.fc-consultantFilter-button'), calendarEl.querySelector('.fc-newEvent-button')].forEach(function(btn) {
            if (btn) {
                btn.style.setProperty('background-color', '#ffffff', 'important');
                btn.style.setProperty('border-color', '#dee2e6', 'important');
                btn.style.setProperty('color', '#212529', 'important');
            }
        });
    }
    
    // Observer para garantir que os estilos são mantidos após mudanças no DOM
    const toolbarObserver = new MutationObserver(function(mutations) {
        applyToolbarStyles();
    });
    
    // Observar mudanças no toolbar
    const headerToolbar = calendarEl.querySelector('.fc-header-toolbar');
    if (headerToolbar) {
        toolbarObserver.observe(headerToolbar, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
    
    // Garantir que os botões estão corretos após render inicial
    setTimeout(function() {
        // Inicializar dropdown de vistas
        initViewSelectorDropdown();
        
        // Aplicar estilos aos botões
        applyToolbarStyles();
        
        // Atualizar botão de data atual
        const currentDateBtn = calendarEl.querySelector('.fc-currentDate-button');
        if (currentDateBtn) {
            const view = calendar.view;
            // Para vista mensal, usar currentStart que representa o início do mês visualizado
            const startDate = view.type === 'dayGridMonth' ? view.currentStart : view.activeStart;
            currentDateBtn.textContent = formatCurrentDateButton(view.type, startDate, view.activeEnd);
            currentDateBtn.style.pointerEvents = 'none';
            currentDateBtn.style.cursor = 'default';
            currentDateBtn.style.fontWeight = '500';
            currentDateBtn.style.color = '#212529';
            currentDateBtn.style.opacity = '1';
        }
        
        // Garantir que prev/next têm apenas ícones
        const prevBtn = calendarEl.querySelector('.fc-prev-button');
        const nextBtn = calendarEl.querySelector('.fc-next-button');
        if (prevBtn) {
            prevBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-left"></span>';
        }
        if (nextBtn) {
            nextBtn.innerHTML = '<span class="fc-icon fc-icon-chevron-right"></span>';
        }
        
        // Esconder título/chunk do center
        const titleEl = calendarEl.querySelector('.fc-toolbar-title');
        if (titleEl) {
            titleEl.closest('.fc-toolbar-chunk')?.style.setProperty('display', 'none', 'important');
        }
        
        // Reaplicar estilos após um pequeno delay para garantir que são aplicados
        setTimeout(function() {
            applyToolbarStyles();
        }, 50);
    }, 100);

    // Inicializar dropdown de consultores após render
    function initConsultantDropdown() {
        const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
        if (!consultantBtn) return;
        
        // Se já foi inicializado, verificar se a estrutura ainda existe
        if (consultantBtn.dataset.initialized === '1') {
            const wrapper = consultantBtn.closest('.dropdown');
            const dropdown = wrapper ? wrapper.querySelector('#consultantDropdown') : null;
            if (wrapper && dropdown) {
                // Estrutura existe, apenas atualizar estado e garantir estilos com important
                updateDropdownActive();
                wrapper.style.setProperty('margin', '0', 'important');
                wrapper.style.setProperty('margin-left', '0', 'important');
                wrapper.style.setProperty('margin-right', '0', 'important');
                wrapper.style.setProperty('padding', '0', 'important');
                wrapper.style.setProperty('display', 'inline-block', 'important');
                consultantBtn.style.setProperty('margin', '0', 'important');
                consultantBtn.style.setProperty('margin-left', '0', 'important');
                consultantBtn.style.setProperty('margin-right', '0', 'important');
                return;
            }
            // Estrutura perdida, re-inicializar
            consultantBtn.dataset.initialized = '';
        }
        
        consultantBtn.dataset.initialized = '1';
        
        // Garantir que temos um wrapper próprio para este dropdown
        let btnParent = consultantBtn.parentElement;
        
        // Se o parent já tem outro dropdown ou não é um dropdown isolado, criar wrapper
        if (btnParent.querySelector('#viewSelectorDropdown') || !btnParent.classList.contains('dropdown') || btnParent.querySelector('.fc-viewSelector-button')) {
            // Criar um wrapper específico para o consultant filter
            const wrapper = document.createElement('div');
            wrapper.className = 'dropdown';
            wrapper.style.setProperty('display', 'inline-block', 'important');
            wrapper.style.setProperty('margin', '0', 'important');
            wrapper.style.setProperty('margin-left', '0', 'important');
            wrapper.style.setProperty('margin-right', '0', 'important');
            wrapper.style.setProperty('padding', '0', 'important');
            consultantBtn.parentElement.insertBefore(wrapper, consultantBtn);
            wrapper.appendChild(consultantBtn);
            btnParent = wrapper;
        } else {
            btnParent.classList.add('dropdown');
            btnParent.style.setProperty('margin', '0', 'important');
            btnParent.style.setProperty('margin-left', '0', 'important');
            btnParent.style.setProperty('margin-right', '0', 'important');
            btnParent.style.setProperty('padding', '0', 'important');
            btnParent.style.setProperty('display', 'inline-block', 'important');
        }
        
        consultantBtn.classList.add('dropdown-toggle');
        consultantBtn.setAttribute('data-bs-toggle', 'dropdown');
        consultantBtn.setAttribute('aria-expanded', 'false');
        consultantBtn.setAttribute('id', 'consultantFilterBtn');
        consultantBtn.style.setProperty('margin', '0', 'important');
        consultantBtn.style.setProperty('margin-left', '0', 'important');
        consultantBtn.style.setProperty('margin-right', '0', 'important');
        
        // Remover dropdown existente se houver (mas não o viewSelectorDropdown)
        const existingDropdown = btnParent.querySelector('#consultantDropdown');
        if (existingDropdown) {
            existingDropdown.remove();
        }
        
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown-menu';
        dropdown.id = 'consultantDropdown';
        dropdown.setAttribute('aria-labelledby', 'consultantFilterBtn');
        
        const allOption = document.createElement('a');
        allOption.className = 'dropdown-item' + (selectedConsultantId === '' ? ' active' : '');
        allOption.href = '#';
        allOption.textContent = 'Toda a equipa';
        allOption.addEventListener('click', function(e) {
            e.preventDefault();
            selectedConsultantId = '';
            consultantFilterIds = [];
            consultantBtn.textContent = '';
            consultantBtn.textContent = 'Toda a equipa';
            calendar.refetchResources();
            updateDropdownActive();
        });
        dropdown.appendChild(allOption);
        
        @foreach($users as $u)
            @if($u->role !== \App\Models\User::ROLE_ADMIN)
            const opt{{ $u->id }} = document.createElement('a');
            opt{{ $u->id }}.className = 'dropdown-item';
            opt{{ $u->id }}.href = '#';
            opt{{ $u->id }}.textContent = '{{ $u->name }}';
            opt{{ $u->id }}.addEventListener('click', function(e) {
                e.preventDefault();
                selectedConsultantId = '{{ $u->id }}';
                consultantFilterIds = ['{{ $u->id }}'];
                consultantBtn.textContent = '';
                consultantBtn.textContent = '{{ $u->name }}';
                calendar.refetchResources();
                updateDropdownActive();
            });
            dropdown.appendChild(opt{{ $u->id }});
            @endif
        @endforeach
        
        btnParent.appendChild(dropdown);
    }

    function updateConsultantFilterButton() {
        const consultantBtn = calendarEl.querySelector('.fc-consultantFilter-button');
        if (consultantBtn && currentViewMode === 'consultant') {
            // Garantir que o dropdown está inicializado
            if (!consultantBtn.dataset.initialized) {
                initConsultantDropdown();
            }
            
            // Atualizar texto do botão (limpar primeiro)
            consultantBtn.textContent = '';
            if (selectedConsultantId && allResources.length > 0) {
                const resource = allResources.find(r => r.id === selectedConsultantId);
                if (resource) {
                    consultantBtn.textContent = resource.title;
                } else {
                    consultantBtn.textContent = 'Toda a equipa';
                }
            } else {
                consultantBtn.textContent = 'Toda a equipa';
            }
            
            // Atualizar estado ativo do dropdown
            updateDropdownActive();
            
            // Garantir que os estilos estão aplicados com important
            consultantBtn.style.setProperty('margin', '0', 'important');
            consultantBtn.style.setProperty('margin-left', '0', 'important');
            consultantBtn.style.setProperty('margin-right', '0', 'important');
            const consultantWrapper = consultantBtn.closest('.dropdown');
            if (consultantWrapper) {
                consultantWrapper.style.setProperty('margin', '0', 'important');
                consultantWrapper.style.setProperty('margin-left', '0', 'important');
                consultantWrapper.style.setProperty('margin-right', '0', 'important');
                consultantWrapper.style.setProperty('padding', '0', 'important');
                consultantWrapper.style.setProperty('display', 'inline-block', 'important');
            }
        }
    }

    function updateDropdownActive() {
        const dropdown = calendarEl.querySelector('#consultantDropdown');
        if (!dropdown) return;
        dropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.classList.remove('active');
        });
        const items = dropdown.querySelectorAll('.dropdown-item');
        if (selectedConsultantId === '') {
            items[0]?.classList.add('active');
        } else {
            const selected = Array.from(items).find(item => {
                const resource = allResources.find(r => r.id === selectedConsultantId);
                return resource && item.textContent === resource.title;
            });
            selected?.classList.add('active');
        }
    }

    // Novo evento: abrir modal com data/hora opcional a partir do dia clicado
    document.getElementById('createEventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('createEventSubmitBtn');
        const originalBtnHtml = submitBtn.innerHTML;
        const eventUserVal = document.getElementById('eventUser').value;
        if (currentUserIsAdmin && !eventUserVal) {
            showToast('Selecione um membro.', 'error');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        const id = document.getElementById('eventId').value;
        const eventType = document.getElementById('eventType').value;
        const payload = {
            title: document.getElementById('eventTitle').value.trim(),
            event_type: eventType,
            start_at: document.getElementById('eventStart').value.replace('T', ' ') + ':00',
            end_at: document.getElementById('eventEnd').value.replace('T', ' ') + ':00',
            description: document.getElementById('eventDescription').value.trim() || null,
            user_id: currentUserIsAdmin ? (eventUserVal || null) : (eventUserVal || '{{ auth()->id() }}')
        };
        if (eventType === 'marcacao') {
            payload.service_id = document.getElementById('eventService').value || null;
        }
        const url = id ? '{{ url('agenda/events') }}/' + id + '/update' : '{{ route('agenda.events.store') }}';
        const method = id ? 'POST' : 'POST';
        function resetSubmitBtn() {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                if (res.event) {
                    if (id) {
                        const ev = calendar.getEventById(id);
                        if (ev) {
                            ev.setStart(res.event.start);
                            ev.setEnd(res.event.end);
                            ev.setProp('title', res.event.title);
                            ev.setExtendedProp('description', res.event.extendedProps.description);
                            ev.setExtendedProp('event_type', res.event.extendedProps.event_type);
                            if (currentViewMode === 'consultant') {
                                calendar.refetchEvents();
                            }
                        }
                    } else {
                        if (currentViewMode === 'consultant' && res.event.extendedProps.user_id) {
                            res.event.resourceId = String(res.event.extendedProps.user_id);
                        }
                        calendar.addEvent(res.event);
                    }
                }
                bootstrap.Modal.getInstance(document.getElementById('createEventModal')).hide();
                document.getElementById('createEventForm').reset();
                document.getElementById('eventId').value = '';
            } else {
                showToast(res.message || 'Erro ao guardar.', 'error');
            }
            resetSubmitBtn();
        })
        .catch(function() {
            showToast('Erro de ligação.', 'error');
            resetSubmitBtn();
        });
    });

    document.getElementById('createEventModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('createEventForm').reset();
        document.getElementById('eventId').value = '';
        document.getElementById('createEventModalLabel').textContent = 'Novo evento';
        document.getElementById('eventService').innerHTML = '<option value="">Selecione o membro primeiro</option>';
        document.getElementById('eventServiceWrap').classList.add('d-none');
    });

    // Sidebar "Novo evento": abrir modal de criar evento
    document.querySelector('.agenda-sidebar-novo-evento, [data-agenda-novo-evento]')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCreateEventModal();
    });

    // Status é guardado ao clicar Guardar no modal eventDetailEditModal

    function eventDetailPopulateTimeOptions(selectedTime) {
        var container = document.querySelector('.event-detail-time-options');
        if (!container) return;
        container.innerHTML = '';
        for (var h = 0; h < 24; h++) {
            for (var m = 0; m < 60; m += 15) {
                var ts = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item event-detail-time-opt' + (ts === selectedTime ? ' active' : '');
                a.dataset.time = ts;
                a.textContent = ts;
                a.addEventListener('click', function(e) { e.preventDefault(); eventDetailApplyNewStartTime(this.dataset.time); });
                container.appendChild(a);
            }
        }
    }
    document.getElementById('eventDetailTimeDropdownMenu')?.addEventListener('click', function(e) {
        var opt = e.target.closest('.event-detail-time-opt');
        if (opt) { e.preventDefault(); eventDetailApplyNewStartTime(opt.dataset.time); }
    });
    document.getElementById('eventDetailTimeToggle')?.addEventListener('show.bs.dropdown', function() {
        var startStr = document.getElementById('eventDetailEditStart')?.value;
        var timeStr = '';
        if (startStr) {
            var d = new Date(startStr);
            var min = d.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        eventDetailPopulateTimeOptions(timeStr);
    });
    document.getElementById('eventDetailTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = document.querySelector('.event-detail-time-options .event-detail-time-opt.active');
        if (active) {
            active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    });

    document.getElementById('novaMarcacaoTimeToggle')?.addEventListener('show.bs.dropdown', function() {
        var startStr = document.getElementById('novaMarcacaoStart')?.value;
        var timeStr = '';
        if (startStr) {
            var d = new Date(startStr);
            var min = d.getMinutes();
            var m = Math.round(min / 15) * 15;
            if (m === 60) { m = 0; }
            timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
        novaMarcacaoPopulateTimeOptions(timeStr);
    });
    document.getElementById('novaMarcacaoTimeToggle')?.addEventListener('shown.bs.dropdown', function() {
        var active = document.querySelector('.nova-marcacao-time-options .nova-marcacao-time-opt.active');
        if (active) {
            active.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    });

    document.getElementById('eventDetailStatusMenu').querySelectorAll('.event-detail-status-opt').forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            e.preventDefault();
            var status = this.dataset.status;
            var labels = { agendado: 'Agendado', confirmado: 'Confirmado', chegou: 'Chegou', iniciado: 'Iniciado', faltou: 'Faltou', cancelado: 'Cancelado' };
            var icons = { agendado: 'ph-clock', confirmado: 'ph-check', chegou: 'ph-map-pin', iniciado: 'ph-play', faltou: 'ph-prohibit', cancelado: 'ph-x-circle' };
            document.getElementById('eventDetailStatus').value = status;
            document.getElementById('eventDetailStatusLabel').textContent = labels[status] || status;
            var iconEl = document.getElementById('eventDetailStatusIcon');
            if (iconEl) {
                var ic = iconEl.querySelector('i');
                if (ic) ic.className = 'me-2 ph ' + (icons[status] || 'ph-clock');
            }
            document.getElementById('eventDetailCancelReasonWrap').classList.toggle('d-none', status !== 'cancelado');
            if (status !== 'cancelado') document.getElementById('eventDetailCancelReason').value = '';
            bootstrap.Dropdown.getInstance(document.getElementById('eventDetailStatusDropdownBtn'))?.hide();
        });
    });

    document.getElementById('eventDetailEditModal').addEventListener('hidden.bs.modal', function() {
        if (!eventDetailWasSaved) {
            var evId = document.getElementById('eventDetailEditId')?.value;
            if (evId && eventDetailOriginalStartAt && eventDetailOriginalEndAt && typeof calendar !== 'undefined') {
                var ev = calendar.getEventById(evId);
                if (ev) {
                    ev.setStart(new Date(eventDetailOriginalStartAt));
                    ev.setEnd(new Date(eventDetailOriginalEndAt));
                }
            }
        }
        eventDetailWasSaved = false;
        eventDetailModalLoading = false;
        eventDetailSelectedClient = null;
        eventDetailSelectedServices = [];
        window._eventDetailPreviousClient = null;
        document.getElementById('eventDetailClientCancelBtn').classList.add('d-none');
        eventDetailOriginalStartAt = null;
        eventDetailOriginalEndAt = null;
    });
});
</script>
@endsection
