@extends('partials.layouts.main')
@section('title', 'Agenda | Beauty CRM')
@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.css">
    <style>
        /* main-content sem padding na agenda */
        body .main-content {
            padding: 0 !important;
        }
        
        /* Esconder breadcrumbs na agenda */
        body .main-breadcrumb,
        body .main-breadcrumb * {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        
        /* Remover padding do app-wrapper na agenda */
        body .app-wrapper {
            padding: 0 !important;
        }
        
        /* Remover margens e padding dos containers da agenda */
        body .app-wrapper .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        
        /* Remover margens e padding do row e col */
        .row.g-0,
        .row.g-0 > .col-12 {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        
        /* Remover margens e padding do card */
        .card.border-0.shadow-none,
        .card.border-0.shadow-none .card-body {
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        #calendar { 
            width: 100%;
            height: calc(100vh - 60px);
            margin: 0;
            padding: 0;
        }
        
        /* Pequeno padding apenas na toolbar do FullCalendar */
        .fc-header-toolbar {
            padding: 1rem 1rem 0.5rem 1rem;
            margin: 0;
        }

        .fc-license-message {
            display: none !important;
        }
        
        /* Margin-bottom na toolbar do FullCalendar */
        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 0.5em;
        }
        
        /* Esconder "Todo o dia" */
        .fc-all-day {
            display: none !important;
        }
        
        /* Linha vermelha indicando a hora atual */
        .fc-timegrid-now-indicator-line {
            border-color: #dc3545 !important;
            border-width: 1px !important;
        }
        
        .fc-timegrid-now-indicator-arrow {
            border-left-color: #dc3545 !important;
            border-right-color: #dc3545 !important;
        }
        
        /* Coluna das horas (esquerda) sem linhas horizontais */
        .fc-timegrid-slot-label,
        .fc-timegrid-axis,
        .fc-timegrid-axis .fc-scrollgrid-sync-inner,
        .fc-timegrid .fc-timegrid-axis td,
        .fc-timegrid .fc-timegrid-axis th {
            border-top: none !important;
            border-bottom: none !important;
        }
        .fc-timegrid-slot-label-frame {
            border: none !important;
        }
        .fc .fc-timegrid .fc-col-header-cell.fc-day-today 
        .fc-col-header-cell-cushion {
            color: #0d6efd !important;
        }
        
        .fc-event { cursor: pointer; color: #fff !important; font-size: 0.75rem !important; font-weight: 400 !important; padding: 2px 5px !important; text-align: left !important; border-radius: 3px !important; }
        .fc-event .fc-event-main,
        .fc-event-main,
        .fc-event .fc-event-title,
        .fc-event-title { color: #fff !important; white-space: normal !important; }
        .fc-event-time { font-weight: 500; margin-right: 0.25rem; }
        .fc-event-content-wrapper { position: relative; width: 100%; height: 100%; }
        .fc-event-status-icon { 
            position: absolute; 
            top: 0px; 
            right: -2px; 
            font-size: 0.75rem; 
            opacity: 1; 
            z-index: 10;
            font-weight: 600;
        }
        .fc-daygrid-event,
        .fc-daygrid-event-harness { min-height: auto !important; }
        .fc-daygrid-event .fc-event-main { padding: 0.15rem 0.35rem !important; font-size: 0.75rem !important; line-height: 1.2 !important; min-height: auto !important; }
        .fc-timegrid-event .fc-event-main { padding: 0.15rem 0.35rem !important; font-size: 0.75rem !important; line-height: 1.2 !important; }
        .fc-timegrid-event-short .fc-event-main { padding: 0.15rem 0.35rem !important; }
        .fc-toolbar-chunk:last-child {
            display: flex !important;
            align-items: center;
            gap: 0 !important;
        }
        .fc-header-toolbar .fc-toolbar-chunk {
            display: flex;
            align-items: center;
        }
        
        /* Primeiro chunk - gap para espaçamento entre botões, sem margem/padding esquerdo */
        .fc-header-toolbar .fc-toolbar-chunk:first-child {
            gap: 0.5rem !important;
            margin-left: 0 !important;
            padding-left: 0 !important;
        }
        
        /* Toolbar: botões e dropdowns sem margens; dropdowns inline-block */
        .fc-toolbar-chunk .dropdown,
        .fc-toolbar-chunk > .dropdown,
        .fc-header-toolbar .dropdown {
            display: inline-block;
            vertical-align: middle;
            margin: 0 !important;
            padding: 0 !important;
        }
        .fc .fc-button,
        .fc-viewSelector-button,
        .fc-consultantFilter-button,
        .fc-newEvent-button,
        .fc-today-button,
        .fc-prev-button,
        .fc-next-button {
            margin: 0 !important;
        }
        
        /* Remover margens e padding dos toolbar-chunks */
        .fc-toolbar-chunk {
            margin: 0 !important;
            padding: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        
        /* Primeiro chunk (left) - gap para espaçamento entre botões, sem margem/padding esquerdo */
        .fc-header-toolbar .fc-toolbar-chunk:first-child,
        .fc-toolbar-chunk:first-child {
            margin-left: 0 !important;
            padding-left: 0 !important;
            gap: 0.5rem !important;
        }
        
        /* Último chunk (right) - sem gap para evitar espaço extra antes do primeiro elemento */
        .fc-header-toolbar .fc-toolbar-chunk:last-child,
        .fc-toolbar-chunk:last-child {
            gap: 0 !important;
            margin-right: 0 !important;
            padding-right: 0 !important;
        }
        
        /* Garantir que o header toolbar não adiciona espaçamento */
        .fc-header-toolbar {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        
        /* Primeiro elemento dentro do primeiro chunk não deve ter margem esquerda */
        .fc-toolbar-chunk:first-child > *:first-child,
        .fc-header-toolbar .fc-toolbar-chunk:first-child > *:first-child,
        .fc-toolbar-chunk:first-child > button:first-child,
        .fc-header-toolbar .fc-toolbar-chunk:first-child > button:first-child {
            margin-left: 0 !important;
            margin: 0 !important;
        }
        
        /* Garantir que os botões individuais não têm margens (o gap do container cria o espaçamento) */
        .fc-toolbar-chunk:first-child > * {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        
        /* Garantir que o botão "Hoje" especificamente não tem margem esquerda e segue o mesmo estilo */
        .fc-today-button,
        .fc .fc-today-button,
        .fc-today-button:not(:disabled),
        .fc .fc-today-button:not(:disabled) {
            margin-left: 0 !important;
            background-color: #ffffff !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
            font-weight: 400 !important;
            border-width: 1px !important;
            border-style: solid !important;
            line-height: 22px !important;
        }
        
        .fc-today-button:hover:not(:disabled),
        .fc .fc-today-button:hover:not(:disabled) {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
        }
        
        .fc-today-button:focus:not(:disabled),
        .fc .fc-today-button:focus:not(:disabled) {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.1) !important;
        }
        
        .fc-toolbar-chunk:first-child .fc-today-button {
            margin-left: 0 !important;
            margin: 0 !important;
        }
        
        .fc-toolbar-title,
        .fc-header-toolbar .fc-toolbar-chunk:nth-child(2) {
            display: none !important;
        }
        
        /* Botão de data atual - visível com estilo */
        .fc-currentDate-button {
            pointer-events: none;
            cursor: default;
            font-weight: 500;
            background: transparent !important;
            border: none !important;
            color: #212529 !important;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            opacity: 1 !important;
        }
        .fc-currentDate-button:hover {
            background: transparent !important;
            color: #212529 !important;
        }
        
        /* Estilo consistente para todos os botões do FullCalendar */
        .fc .fc-button-primary,
        .fc .fc-button-primary:not(:disabled),
        .fc .fc-button-primary:not(:disabled):active,
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc button.fc-today-button,
        .fc button.fc-today-button:not(:disabled),
        .fc button.fc-today-button:not(:disabled):active,
        .fc .fc-today-button,
        .fc .fc-today-button:not(:disabled),
        .fc .fc-today-button:not(:disabled):active,
        .fc .fc-prev-button,
        .fc .fc-next-button,
        .fc .fc-viewSelector-button,
        .fc .fc-consultantFilter-button,
        .fc .fc-newEvent-button,
        .fc .fc-button-group .fc-button-primary {
            background-color: #ffffff !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
            font-weight: 400;
        }
        
        .fc .fc-button-primary:hover:not(:disabled),
        .fc button.fc-today-button:hover:not(:disabled),
        .fc .fc-today-button:hover:not(:disabled),
        .fc .fc-prev-button:hover:not(:disabled),
        .fc .fc-next-button:hover:not(:disabled),
        .fc .fc-viewSelector-button:hover:not(:disabled),
        .fc .fc-consultantFilter-button:hover:not(:disabled),
        .fc .fc-newEvent-button:hover:not(:disabled),
        .fc .fc-button-group .fc-button-primary:hover:not(:disabled) {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
        }
        
        .fc .fc-button-primary:focus:not(:disabled),
        .fc button.fc-today-button:focus:not(:disabled),
        .fc .fc-today-button:focus:not(:disabled),
        .fc .fc-prev-button:focus:not(:disabled),
        .fc .fc-next-button:focus:not(:disabled),
        .fc .fc-viewSelector-button:focus:not(:disabled),
        .fc .fc-consultantFilter-button:focus:not(:disabled),
        .fc .fc-newEvent-button:focus:not(:disabled),
        .fc .fc-button-group .fc-button-primary:focus:not(:disabled) {
            background-color: #f8f9fa !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.1) !important;
        }
        
        .fc .fc-button-primary:not(:disabled):active,
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc button.fc-today-button:not(:disabled):active,
        .fc .fc-today-button:not(:disabled):active,
        .fc .fc-prev-button:not(:disabled):active,
        .fc .fc-next-button:not(:disabled):active,
        .fc .fc-viewSelector-button:not(:disabled):active,
        .fc .fc-consultantFilter-button:not(:disabled):active,
        .fc .fc-newEvent-button:not(:disabled):active,
        .fc .fc-button-group .fc-button-primary:not(:disabled):active,
        .fc .fc-button-group .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #e9ecef !important;
            border-color: #dee2e6 !important;
            color: #212529 !important;
        }
        
        /* Ícones dos botões prev/next - cor escura */
        .fc-prev-button .fc-icon,
        .fc-next-button .fc-icon {
            color: #212529 !important;
        }
        
        /* Dropdown toggle - estilo consistente */
        .fc-viewSelector-button.dropdown-toggle::after,
        .fc-consultantFilter-button.dropdown-toggle::after {
            border-top-color: #212529 !important;
        }
        
        /* Garantir que os cabeçalhos dos dias estão visíveis */
        .fc-col-header-cell,
        .fc-day-header {
            visibility: visible !important;
        }
        
        .fc-col-header-cell-cushion,
        .fc-day-header-cushion {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        /* Alinhar cabeçalhos à esquerda apenas na vista mensal */
        .fc-dayGridMonth .fc-col-header-cell-cushion,
        .fc-dayGridMonth .fc-day-header-cushion {
            text-align: left !important;
            padding-left: 0.5rem !important;
        }
        
        /* Alinhar números das células à esquerda */
        .fc-daygrid-day-number {
            text-align: left !important;
            padding-left: 0.5rem !important;
        }

        .fc .fc-day-today {
            background-color: #ffffff !important;
        }
        
        /* Células do mês com altura uniforme */
        .fc-dayGridMonth-view .fc-scrollgrid-sync-table {
            height: 100%;
        }
        .fc-dayGridMonth-view .fc-daygrid-day-frame {
            min-height: 7rem;
        }
        .fc-dayGridMonth-view .fc-daygrid-day {
            min-height: 7rem;
        }
        .fc-dayGridMonth-view .fc-daygrid-week {
            min-height: 7rem;
        }
        
        /* Dia atual - círculo azul com número branco (apenas na vista mensal) */
        .fc-dayGridMonth-view .fc-day-today .fc-daygrid-day-number {
            color: #0d6efd !important;
            display: inline-flex !important;
            font-weight: 600 !important;
            padding: 0 0 0 6px !important;
        }
        

        /* Cabeçalho do recurso (por consultor): foto à esquerda do nome */
        .fc-resource-consultant-label {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }
        .fc-resource-consultant-avatar {
            width: 20px !important;
            height: 20px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            flex-shrink: 0 !important;
        }
        .fc-resource-consultant-name {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        /* Menos padding inferior no header da vista por consultor */
        .fc-resourceTimeGridDay-view .fc-col-header-cell {
            padding-bottom: 0rem !important;
        }
        
        /* Alinhar th à esquerda */
        .fc th {
            text-align: left !important;
        }
        
        /* Remover flex-direction de fc-daygrid-day-top */
        .fc .fc-daygrid-day-top {
            flex-direction: unset !important;
            display: block !important;
        }
        
        /* Ocultar o dot dos eventos (todas as vistas) */
        .fc-daygrid-event-dot {
            display: none !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
        }
        
        /* === 1) Hover nas células do calendário (apenas na área vazia, não sobre eventos) === */
        /* Vista mensal: hover no frame do dia */
        .fc-daygrid-day-frame:hover,
        .fc-daygrid-day:hover .fc-daygrid-day-frame {
            background-color: #f0f4f8 !important;
        }
        
        /* Modal de detalhes do evento completo - layout moderno */
        #eventDetailModal .modal-dialog {
            max-width: 800px;
        }
        #eventDetailModal .modal-header {
            padding-bottom: 0.5rem;
            padding-top: 1rem;
            align-items: flex-start;
        }
        #eventDetailModal .modal-header .modal-title {
            flex: 1;
            padding-right: 0.5rem;
        }
        #eventDetailModal .modal-body {
            padding-top: 0;
        }
        #eventDetailModal h4 {
            font-size: 1.5rem;
            line-height: 1.3;
        }
        #eventDetailModal .badge {
            font-size: 0.875rem;
            padding: 0.4rem 0.7rem;
            font-weight: 500;
        }
        #eventDetailModal i {
            font-size: 1.1rem;
        }
        #eventDetailModal .text-uppercase {
            letter-spacing: 0.5px;
            font-size: 0.75rem;
        }
        @media (max-width: 991.98px) {
            #eventDetailModal .col-lg-6 {
                border-start: none !important;
                border-top: 1px solid #dee2e6;
                margin-top: 1.5rem;
                padding-top: 1.5rem !important;
                padding-left: 0 !important;
            }
        }
        
        /* Modal de detalhes do evento simples - layout compacto */
        #eventDetailModalSimple .modal-dialog {
            max-width: 500px;
        }
        #eventDetailModalSimple .modal-header {
            padding-bottom: 0.5rem;
            padding-top: 1rem;
            align-items: flex-start;
        }
        #eventDetailModalSimple .modal-header .modal-title {
            flex: 1;
            padding-right: 0.5rem;
        }
        #eventDetailModalSimple .modal-body {
            padding-top: 0;
        }
        #eventDetailModalSimple h4 {
            font-size: 1.5rem;
            line-height: 1.3;
        }
        #eventDetailModalSimple .badge {
            font-size: 0.875rem;
            padding: 0.4rem 0.7rem;
            font-weight: 500;
        }
        #eventDetailModalSimple i {
            font-size: 1.1rem;
        }
        
        /* Modal de criar/editar evento - mesmo estilo de header */
        #createEventModal .modal-header {
            padding-bottom: 0.5rem;
            padding-top: 1rem;
            align-items: flex-start;
        }
        #createEventModal .modal-header .modal-title {
            flex: 1;
            padding-right: 0.5rem;
        }
        #createEventModal .modal-body {
            padding-top: 0;
        }
        #createEventModal h4 {
            font-size: 1.5rem;
            line-height: 1.3;
        }
    </style>
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

<!-- Modal: Criar / Editar evento (apenas manual/outro) -->
<div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2 d-flex align-items-start justify-content-between">
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
                            <option value="manual">Manual</option>
                            <option value="outro">Outro</option>
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
                    <div class="mb-0">
                        <label for="eventUser" class="form-label">Responsável</label>
                        <select class="form-select" id="eventUser" name="user_id">
                            <option value="">Eu ({{ auth()->user()->name }})</option>
                            @foreach($users as $u)
                                @if($u->id !== auth()->id())
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endif
                            @endforeach
                        </select>
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

<!-- Modal: Detalhes do evento simples (sem leads/visitas) -->
<div class="modal fade" id="eventDetailModalSimple" tabindex="-1" aria-labelledby="eventDetailModalSimpleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2 d-flex align-items-start justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold" id="detailTitleSimple">—</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-5">
                
                <!-- Data e hora -->
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="ph-duotone ph-calendar text-muted me-2"></i>
                        <span class="small text-muted" id="detailDateSimple">—</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="ph-duotone ph-clock text-muted me-2"></i>
                        <span class="small text-muted" id="detailTimeSimple">—</span>
                    </div>
                </div>
                
                <!-- Tipo de evento -->
                <div class="mb-3">
                    <div class="d-flex align-items-center">
                        <i class="ph-duotone ph-tag text-muted me-2"></i>
                        <span class="badge bg-primary" id="detailTypeBadgeSimple">—</span>
                    </div>
                </div>
                
                <!-- Estado do evento -->
                <div class="mb-3">
                    <label for="detailStatusSimple" class="form-label small text-muted mb-1">Estado</label>
                    <select class="form-select form-select-sm" id="detailStatusSimple">
                        <option value="agendado">Agendado</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="chegou">Chegou</option>
                        <option value="iniciado">Iniciado</option>
                        <option value="faltou">Faltou</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                
                <!-- Responsável -->
                <div class="mb-3">
                    <div class="d-flex align-items-center">
                        <i class="ph-duotone ph-user text-muted me-2"></i>
                        <div class="d-flex align-items-center">
                            <img id="detailUserAvatarSimple" src="" alt="" class="rounded-circle me-2 d-none" style="width: 20px; height: 20px; object-fit: cover;">
                            <span class="small text-muted" id="detailUserSimple">—</span>
                        </div>
                    </div>
                </div>
                
                <!-- Descrição -->
                <div id="detailDescriptionWrapSimple" class="mb-0 d-none">
                    <p class="mb-0 text-muted" id="detailDescriptionSimple">—</p>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary d-none" id="btnEditEventSimple">
                    <i class="ph ph-pencil-simple me-1"></i> Editar
                </button>
                <button type="button" class="btn btn-danger d-none" id="btnDeleteEventSimple">
                    <i class="ph ph-trash me-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detalhes do evento completo (com leads/visitas) -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2 d-flex align-items-start justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold" id="detailTitle">—</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-5">
                <div class="row g-0">
                    <!-- Coluna esquerda: Informações do evento (igual ao modal simples) -->
                    <div class="col-lg-6 pe-lg-4">
                        
                        <!-- Data e hora -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="ph-duotone ph-calendar text-muted me-2"></i>
                                <span class="small text-muted" id="detailDate">—</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="ph-duotone ph-clock text-muted me-2"></i>
                                <span class="small text-muted" id="detailTime">—</span>
                            </div>
                        </div>
                        
                        <!-- Tipo de evento -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <i class="ph-duotone ph-tag text-muted me-2"></i>
                                <span class="badge bg-primary" id="detailTypeBadge">—</span>
                            </div>
                        </div>
                        
                        <!-- Estado do evento -->
                        <div class="mb-3">
                            <label for="detailStatus" class="form-label small text-muted mb-1">Estado</label>
                            <select class="form-select form-select-sm" id="detailStatus">
                                <option value="agendado">Agendado</option>
                                <option value="confirmado">Confirmado</option>
                                <option value="chegou">Chegou</option>
                                <option value="iniciado">Iniciado</option>
                                <option value="faltou">Faltou</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                        
                        <!-- Responsável -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <i class="ph-duotone ph-user text-muted me-2"></i>
                                <div class="d-flex align-items-center">
                                    <img id="detailUserAvatar" src="" alt="" class="rounded-circle me-2 d-none" style="width: 20px; height: 20px; object-fit: cover;">
                                    <span class="small text-muted" id="detailUser">—</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descrição -->
                        <div id="detailDescriptionWrap" class="mb-0 d-none">
                            <p class="mb-0 text-muted" id="detailDescription">—</p>
                        </div>
                    </div>
                    
                    <!-- Coluna direita: Detalhes da lead/visita -->
                    <div class="col-lg-6 ps-lg-4 border-start">
                        <div id="detailVisit" class="d-none">
                            <h6 class="text-muted text-uppercase small fw-semibold mb-3">
                                <i class="ph-duotone ph-house me-1"></i> Detalhes da visita
                            </h6>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ph-duotone ph-user text-muted me-2"></i>
                                    <span class="small text-muted" id="detailClient">—</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ph-duotone ph-house text-muted me-2"></i>
                                    <span class="small text-muted" id="detailProperty">—</span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <a id="linkOpportunity" href="#" class="btn btn-sm btn-outline-primary">
                                    <i class="ph-duotone ph-briefcase me-1"></i> Ficha da Oportunidade
                                </a>
                                <a id="linkProperty" href="#" class="btn btn-sm btn-outline-secondary">
                                    <i class="ph-duotone ph-house me-1"></i> Ficha do Imóvel
                                </a>
                            </div>
                        </div>
                        
                        <div id="detailLead" class="d-none">
                            <h6 class="text-muted text-uppercase small fw-semibold mb-3">
                                <i class="ph-duotone ph-file-text me-1"></i> Detalhes da lead
                            </h6>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ph-duotone ph-user text-muted me-2"></i>
                                    <span class="small text-muted" id="detailLeadName">—</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="ph-duotone ph-phone text-muted me-2"></i>
                                    <span class="small text-muted" id="detailLeadContact">—</span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a id="linkLead" href="#" class="btn btn-sm btn-outline-primary">
                                    <i class="ph-duotone ph-file-text me-1"></i> Ficha da Lead
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary d-none" id="btnEditEvent">
                    <i class="ph ph-pencil-simple me-1"></i> Editar
                </button>
                <button type="button" class="btn btn-danger d-none" id="btnDeleteEvent">
                    <i class="ph ph-trash me-1"></i> Eliminar
                </button>
            </div>
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

    /**
     * Abre o modal de criar evento (reutilizado pelo botão e pelo clique na célula).
     * Opcionalmente preenche início e fim no formato datetime-local (YYYY-MM-DDTHH:mm).
     */
    function openCreateEventModal(initialStart, initialEnd) {
        if (initialStart) document.getElementById('eventStart').value = initialStart;
        if (initialEnd) document.getElementById('eventEnd').value = initialEnd;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('createEventModal')).show();
    }

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
        allDaySlot: false,
        nowIndicator: true,
        scrollTime: new Date().toTimeString().slice(0, 5) + ':00',
        scrollTimeReset: false,
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        dayMaxEvents: 2,
        dayMaxEventRows: 2,
        eventContent: function(arg) {
            const start = arg.event.start;
            const end = arg.event.end;
            const fmt = function(d) { return d ? (String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')) : ''; };
            const viewType = arg.view && arg.view.type ? arg.view.type : '';
            const isTimeView = viewType === 'timeGridWeek' || viewType === 'resourceTimeGridDay';
            const startStr = fmt(start);
            const endStr = fmt(end);
            const timeStr = isTimeView && startStr && endStr ? (startStr + ' - ' + endStr) : startStr;
            const title = (arg.event.title || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const extProps = arg.event.extendedProps || {};
            const statusIcon = extProps.status_icon || null;
            let iconHtml = '';
            if (statusIcon) {
                iconHtml = '<i class="' + statusIcon + ' fc-event-status-icon"></i>';
            }
            return { html: '<div class="fc-event-content-wrapper"><span class="fc-event-time">' + timeStr + '</span> <span class="fc-event-title">' + title + '</span>' + iconHtml + '</div>' };
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
        /* === 2) Criar evento ao clicar numa célula: abre o mesmo modal com data/hora da célula === */
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
            openCreateEventModal(toLocalDateTimeStr(startDate), toLocalDateTimeStr(endDate));
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
                const hasAssociations = !!(data.visit || data.lead);
                const modalEl = hasAssociations ? document.getElementById('eventDetailModal') : document.getElementById('eventDetailModalSimple');
                if (modalEl.classList.contains('show')) {
                    eventDetailModalLoading = false;
                    return;
                }
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                
                if (hasAssociations) {
                    // Modal completo (com leads/visitas)
                    document.getElementById('detailTitle').textContent = data.title || '—';
                    const startDate = data.start_at ? new Date(data.start_at) : null;
                    const endDate = data.end_at ? new Date(data.end_at) : null;
                    const days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                    const months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                    if (startDate) {
                        const dateStr = days[startDate.getDay()] + ', ' + startDate.getDate() + ' de ' + months[startDate.getMonth()] + ' de ' + startDate.getFullYear();
                        document.getElementById('detailDate').textContent = dateStr;
                        const timeStr = String(startDate.getHours()).padStart(2, '0') + ':' + String(startDate.getMinutes()).padStart(2, '0');
                        if (endDate && endDate.getTime() !== startDate.getTime()) {
                            const endTimeStr = String(endDate.getHours()).padStart(2, '0') + ':' + String(endDate.getMinutes()).padStart(2, '0');
                            document.getElementById('detailTime').textContent = timeStr + ' - ' + endTimeStr;
                        } else {
                            document.getElementById('detailTime').textContent = timeStr;
                        }
                    } else {
                        document.getElementById('detailDate').textContent = '—';
                        document.getElementById('detailTime').textContent = '—';
                    }
                    const typeLabel = data.event_type_label || data.event_type || '—';
                    const typeBadge = document.getElementById('detailTypeBadge');
                    typeBadge.textContent = typeLabel;
                    typeBadge.className = 'badge';
                    if (data.event_type === 'lead') {
                        typeBadge.classList.add('bg-info');
                    } else if (data.event_type === 'visita' || data.event_type === 'visit') {
                        typeBadge.classList.add('bg-success');
                    } else {
                        typeBadge.classList.add('bg-primary');
                    }
                    const userAvatarEl = document.getElementById('detailUserAvatar');
                    const userNameEl = document.getElementById('detailUser');
                    if (userAvatarEl && userNameEl) {
                        if (data.user_avatar_url) {
                            userAvatarEl.src = data.user_avatar_url;
                            userAvatarEl.classList.remove('d-none');
                        } else {
                            userAvatarEl.classList.add('d-none');
                        }
                        userNameEl.textContent = data.user_name || '—';
                    }
                    const descWrap = document.getElementById('detailDescriptionWrap');
                    const descEl = document.getElementById('detailDescription');
                    if (data.description && data.description.trim()) {
                        descEl.textContent = data.description;
                        descWrap.classList.remove('d-none');
                    } else {
                        descWrap.classList.add('d-none');
                    }
                    document.getElementById('eventDetailModal').dataset.eventId = id;
                    document.getElementById('eventDetailModal').dataset.eventDeletable = data.is_deletable ? '1' : '0';
                    document.getElementById('eventDetailModal').dataset.eventTimeEditable = data.is_time_editable ? '1' : '0';
                    document.getElementById('eventDetailModal').dataset.eventSourceEditable = data.is_source_editable ? '1' : '0';
                    const statusSelect = document.getElementById('detailStatus');
                    if (statusSelect) {
                        statusSelect.value = data.status || 'agendado';
                    }
                    const visitEl = document.getElementById('detailVisit');
                    const leadEl = document.getElementById('detailLead');
                    visitEl.classList.add('d-none');
                    leadEl.classList.add('d-none');
                    if (data.visit) {
                        visitEl.classList.remove('d-none');
                        document.getElementById('detailClient').textContent = data.visit.client_name || '—';
                        document.getElementById('detailProperty').textContent = (data.visit.property_title || '') + (data.visit.property_reference ? ' (' + data.visit.property_reference + ')' : '');
                        document.getElementById('linkOpportunity').href = data.visit.opportunity_id ? '{{ url('opportunities') }}/' + data.visit.opportunity_id : '#';
                        document.getElementById('linkProperty').href = data.visit.property_id ? '{{ url('properties') }}/' + data.visit.property_id : '#';
                    }
                    if (data.lead) {
                        leadEl.classList.remove('d-none');
                        document.getElementById('detailLeadName').textContent = data.lead.name || '—';
                        document.getElementById('detailLeadContact').textContent = [data.lead.email, data.lead.phone].filter(Boolean).join(' · ') || '—';
                        document.getElementById('linkLead').href = '{{ url('leads') }}/' + data.lead.id;
                    }
                    document.getElementById('btnEditEvent').classList.toggle('d-none', !data.is_source_editable);
                    document.getElementById('btnDeleteEvent').classList.toggle('d-none', !data.is_deletable);
                } else {
                    // Modal simples (sem leads/visitas)
                    document.getElementById('detailTitleSimple').textContent = data.title || '—';
                    const startDate = data.start_at ? new Date(data.start_at) : null;
                    const endDate = data.end_at ? new Date(data.end_at) : null;
                    const days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                    const months = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                    if (startDate) {
                        const dateStr = days[startDate.getDay()] + ', ' + startDate.getDate() + ' de ' + months[startDate.getMonth()] + ' de ' + startDate.getFullYear();
                        document.getElementById('detailDateSimple').textContent = dateStr;
                        const timeStr = String(startDate.getHours()).padStart(2, '0') + ':' + String(startDate.getMinutes()).padStart(2, '0');
                        if (endDate && endDate.getTime() !== startDate.getTime()) {
                            const endTimeStr = String(endDate.getHours()).padStart(2, '0') + ':' + String(endDate.getMinutes()).padStart(2, '0');
                            document.getElementById('detailTimeSimple').textContent = timeStr + ' - ' + endTimeStr;
                        } else {
                            document.getElementById('detailTimeSimple').textContent = timeStr;
                        }
                    } else {
                        document.getElementById('detailDateSimple').textContent = '—';
                        document.getElementById('detailTimeSimple').textContent = '—';
                    }
                    const typeLabel = data.event_type_label || data.event_type || '—';
                    const typeBadgeSimple = document.getElementById('detailTypeBadgeSimple');
                    typeBadgeSimple.textContent = typeLabel;
                    typeBadgeSimple.className = 'badge';
                    if (data.event_type === 'lead') {
                        typeBadgeSimple.classList.add('bg-info');
                    } else if (data.event_type === 'visita' || data.event_type === 'visit') {
                        typeBadgeSimple.classList.add('bg-success');
                    } else {
                        typeBadgeSimple.classList.add('bg-primary');
                    }
                    const userAvatarElSimple = document.getElementById('detailUserAvatarSimple');
                    const userNameElSimple = document.getElementById('detailUserSimple');
                    if (userAvatarElSimple && userNameElSimple) {
                        if (data.user_avatar_url) {
                            userAvatarElSimple.src = data.user_avatar_url;
                            userAvatarElSimple.classList.remove('d-none');
                        } else {
                            userAvatarElSimple.classList.add('d-none');
                        }
                        userNameElSimple.textContent = data.user_name || '—';
                    }
                    const descWrapSimple = document.getElementById('detailDescriptionWrapSimple');
                    const descElSimple = document.getElementById('detailDescriptionSimple');
                    if (data.description && data.description.trim()) {
                        descElSimple.textContent = data.description;
                        descWrapSimple.classList.remove('d-none');
                    } else {
                        descWrapSimple.classList.add('d-none');
                    }
                    document.getElementById('eventDetailModalSimple').dataset.eventId = id;
                    document.getElementById('eventDetailModalSimple').dataset.eventDeletable = data.is_deletable ? '1' : '0';
                    document.getElementById('eventDetailModalSimple').dataset.eventTimeEditable = data.is_time_editable ? '1' : '0';
                    document.getElementById('eventDetailModalSimple').dataset.eventSourceEditable = data.is_source_editable ? '1' : '0';
                    const statusSelectSimple = document.getElementById('detailStatusSimple');
                    if (statusSelectSimple) {
                        statusSelectSimple.value = data.status || 'agendado';
                    }
                    document.getElementById('btnEditEventSimple').classList.toggle('d-none', !data.is_source_editable);
                    document.getElementById('btnDeleteEventSimple').classList.toggle('d-none', !data.is_deletable);
                }
                modal.show();
                eventDetailModalLoading = false;
            })
            .catch(function(error) {
                console.error('Erro ao carregar detalhes do evento:', error);
                alert('Erro ao carregar detalhes do evento.');
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
                    alert(res.message || 'Erro ao atualizar.');
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
                    alert(res.message || 'Erro ao atualizar.');
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
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A guardar...';
        const id = document.getElementById('eventId').value;
        const payload = {
            title: document.getElementById('eventTitle').value.trim(),
            event_type: document.getElementById('eventType').value,
            start_at: document.getElementById('eventStart').value.replace('T', ' ') + ':00',
            end_at: document.getElementById('eventEnd').value.replace('T', ' ') + ':00',
            description: document.getElementById('eventDescription').value.trim() || null,
            user_id: document.getElementById('eventUser').value ? document.getElementById('eventUser').value : '{{ auth()->id() }}'
        };
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
                alert(res.message || 'Erro ao guardar.');
            }
            resetSubmitBtn();
        })
        .catch(function() {
            alert('Erro de ligação.');
            resetSubmitBtn();
        });
    });

    document.getElementById('btnDeleteEvent').addEventListener('click', function() {
        const modal = document.getElementById('eventDetailModal');
        const id = modal.dataset.eventId;
        if (!id || modal.dataset.eventDeletable !== '1') return;
        if (!confirm('Eliminar este evento?')) return;
        fetch('{{ url('agenda/events') }}/' + id, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                calendar.getEventById(id)?.remove();
                bootstrap.Modal.getInstance(modal).hide();
            } else {
                alert(res.message || 'Não foi possível eliminar.');
            }
        });
    });

    document.getElementById('btnEditEvent').addEventListener('click', function() {
        const modal = document.getElementById('eventDetailModal');
        const id = modal.dataset.eventId;
        if (!id) return;
        bootstrap.Modal.getInstance(modal).hide();
        document.getElementById('eventId').value = id;
        document.getElementById('createEventModalLabel').textContent = 'Editar evento';
        fetch('{{ url('agenda/events') }}/' + id, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(function(data) {
            document.getElementById('eventTitle').value = data.title || '';
            document.getElementById('eventType').value = data.event_type || 'manual';
            document.getElementById('eventDescription').value = data.description || '';
            if (data.user_id && String(data.user_id) !== '{{ auth()->id() }}') {
                document.getElementById('eventUser').value = String(data.user_id);
            } else {
                document.getElementById('eventUser').value = '';
            }
            if (data.start_at) {
                const d = new Date(data.start_at);
                document.getElementById('eventStart').value = d.toISOString().slice(0, 16);
            }
            if (data.end_at) {
                const d = new Date(data.end_at);
                document.getElementById('eventEnd').value = d.toISOString().slice(0, 16);
            }
            new bootstrap.Modal(document.getElementById('createEventModal')).show();
        });
    });

    document.getElementById('btnDeleteEventSimple').addEventListener('click', function() {
        const modal = document.getElementById('eventDetailModalSimple');
        const id = modal.dataset.eventId;
        if (!id || modal.dataset.eventDeletable !== '1') return;
        if (!confirm('Eliminar este evento?')) return;
        fetch('{{ url('agenda/events') }}/' + id, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                calendar.getEventById(id)?.remove();
                bootstrap.Modal.getInstance(modal).hide();
            } else {
                alert(res.message || 'Não foi possível eliminar.');
            }
        });
    });

    document.getElementById('btnEditEventSimple').addEventListener('click', function() {
        const modal = document.getElementById('eventDetailModalSimple');
        const id = modal.dataset.eventId;
        if (!id) return;
        bootstrap.Modal.getInstance(modal).hide();
        document.getElementById('eventId').value = id;
        document.getElementById('createEventModalLabel').textContent = 'Editar evento';
        fetch('{{ url('agenda/events') }}/' + id, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(function(data) {
            document.getElementById('eventTitle').value = data.title || '';
            document.getElementById('eventType').value = data.event_type || 'manual';
            document.getElementById('eventDescription').value = data.description || '';
            if (data.user_id && String(data.user_id) !== '{{ auth()->id() }}') {
                document.getElementById('eventUser').value = String(data.user_id);
            } else {
                document.getElementById('eventUser').value = '';
            }
            if (data.start_at) {
                const d = new Date(data.start_at);
                document.getElementById('eventStart').value = d.toISOString().slice(0, 16);
            }
            if (data.end_at) {
                const d = new Date(data.end_at);
                document.getElementById('eventEnd').value = d.toISOString().slice(0, 16);
            }
            new bootstrap.Modal(document.getElementById('createEventModal')).show();
        });
    });

    document.getElementById('createEventModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('createEventForm').reset();
        document.getElementById('eventId').value = '';
        document.getElementById('createEventModalLabel').textContent = 'Novo evento';
    });

    // Sidebar "Novo evento": abrir modal de criar evento
    document.querySelector('.agenda-sidebar-novo-evento, [data-agenda-novo-evento]')?.addEventListener('click', function(e) {
        e.preventDefault();
        openCreateEventModal();
    });

    // Atualizar status do evento (modal completo)
    const detailStatusEl = document.getElementById('detailStatus');
    if (detailStatusEl) {
        detailStatusEl.addEventListener('change', function() {
            const modal = document.getElementById('eventDetailModal');
            const eventId = modal.dataset.eventId;
            const newStatus = this.value;
            if (!eventId || !newStatus) return;
            
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('{{ url('agenda/events') }}/' + eventId + '/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ status: newStatus })
            })
            .then(r => r.json())
            .then(function(res) {
                if (res.success) {
                    // Atualizar evento no calendário
                    const event = calendar.getEventById(eventId);
                    if (event) {
                        event.setExtendedProp('status', res.status);
                        event.setExtendedProp('status_label', res.status_label);
                        event.setExtendedProp('status_icon', res.status_icon);
                        // Forçar re-render do evento para atualizar o ícone
                        calendar.render();
                    }
                } else {
                    alert(res.message || 'Não foi possível atualizar o estado.');
                    // Reverter o valor do select
                    const statusSelect = document.getElementById('detailStatus');
                    if (statusSelect) {
                        const event = calendar.getEventById(eventId);
                        if (event) {
                            statusSelect.value = event.extendedProps.status || 'agendado';
                        }
                    }
                }
            })
            .catch(function(error) {
                console.error('Erro ao atualizar estado:', error);
                alert('Erro ao atualizar o estado do evento.');
                // Reverter o valor do select
                const statusSelect = document.getElementById('detailStatus');
                if (statusSelect) {
                    const event = calendar.getEventById(eventId);
                    if (event) {
                        statusSelect.value = event.extendedProps.status || 'agendado';
                    }
                }
            });
        });
    }

    // Atualizar status do evento (modal simples)
    const detailStatusSimpleEl = document.getElementById('detailStatusSimple');
    if (detailStatusSimpleEl) {
        detailStatusSimpleEl.addEventListener('change', function() {
            const modal = document.getElementById('eventDetailModalSimple');
            const eventId = modal.dataset.eventId;
            const newStatus = this.value;
            if (!eventId || !newStatus) return;
            
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('{{ url('agenda/events') }}/' + eventId + '/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ status: newStatus })
            })
            .then(r => r.json())
            .then(function(res) {
                if (res.success) {
                    // Atualizar evento no calendário
                    const event = calendar.getEventById(eventId);
                    if (event) {
                        event.setExtendedProp('status', res.status);
                        event.setExtendedProp('status_label', res.status_label);
                        event.setExtendedProp('status_icon', res.status_icon);
                        // Forçar re-render do evento para atualizar o ícone
                        calendar.render();
                    }
                } else {
                    alert(res.message || 'Não foi possível atualizar o estado.');
                    // Reverter o valor do select
                    const statusSelect = document.getElementById('detailStatusSimple');
                    if (statusSelect) {
                        const event = calendar.getEventById(eventId);
                        if (event) {
                            statusSelect.value = event.extendedProps.status || 'agendado';
                        }
                    }
                }
            })
            .catch(function(error) {
                console.error('Erro ao atualizar estado:', error);
                alert('Erro ao atualizar o estado do evento.');
                // Reverter o valor do select
                const statusSelect = document.getElementById('detailStatusSimple');
                if (statusSelect) {
                    const event = calendar.getEventById(eventId);
                    if (event) {
                        statusSelect.value = event.extendedProps.status || 'agendado';
                    }
                }
            });
        });
    }

    document.getElementById('eventDetailModal').addEventListener('hidden.bs.modal', function() {
        eventDetailModalLoading = false;
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
    
    document.getElementById('eventDetailModalSimple').addEventListener('hidden.bs.modal', function() {
        eventDetailModalLoading = false;
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
});
</script>
@endsection
