@extends('partials.layouts.main')
@section('title', 'Agenda | Beauty CRM')
@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('template/css/agenda.css') }}?v={{ file_exists(public_path('template/css/agenda.css')) ? filemtime(public_path('template/css/agenda.css')) : time() }}">
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

<!-- Quickview do evento (ao passar o rato por cima do evento) -->
<div id="agendaEventQuickview" role="tooltip" aria-label="Detalhe do evento" class="agenda-event-quickview"></div>

<!-- Modal: Nova marcação (inspirado em apps-support ticket detail) -->
<div class="modal fade" id="novaMarcacaoModal" tabindex="-1" aria-labelledby="novaMarcacaoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header pb-3 d-flex align-items-center justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold d-flex flex-wrap align-items-center gap-2">
                    <span class="dropdown">
                        <span class="event-detail-time-toggle dropdown-toggle" id="novaMarcacaoDateToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                            <span id="novaMarcacaoEditTitleDay">—</span>
                        </span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="novaMarcacaoDateDropdownMenu">
                            <div class="picker-inline-wrapper" id="novaMarcacaoDatePickerWrap"></div>
                        </div>
                    </span>
                    <span class="dropdown ms-3">
                        <span class="event-detail-time-toggle dropdown-toggle" id="novaMarcacaoTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">—</span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="novaMarcacaoTimeDropdownMenu">
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
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-lg-5">
                            <div class="nova-marcacao-sidebar">
                                <div class="nova-marcacao-section nova-marcacao-section-client">
                                    <div id="novaMarcacaoClientAddWrap" class="nova-marcacao-client-add-wrap text-center">
                                        <div class="nova-marcacao-client-add-icon mb-3"><i class="ph ph-user-circle-plus"></i></div>
                                        <strong id="novaMarcacaoAddClientBtn" class="d-block">Pesquisar cliente</strong>
                                    </div>
                                    <div id="novaMarcacaoClientSearchWrap" class="mb-0">
                                        <div class="d-flex gap-2 align-items-center mb-3">
                                            <input type="text" id="novaMarcacaoClientSearch" class="form-control form-control-sm flex-grow-1" placeholder="Nome, telemóvel, email..." autocomplete="off">
                                            <button type="button" class="btn btn-light btn-sm d-none" id="novaMarcacaoClientCancelBtn">Cancelar</button>
                                        </div>
                                        <div class="text-center mb-2">
                                            <a href="#" class="nova-marcacao-create-client-link" id="novaMarcacaoCreateClientBtn">
                                                <i class="ph ph-plus"></i> criar novo cliente
                                            </a>
                                        </div>
                                        <div id="novaMarcacaoClientResults" class="nova-marcacao-client-results mb-0"></div>
                                    </div>
                                    <div id="novaMarcacaoClientSelected" class="nova-marcacao-client-card d-none text-center">
                                        <div class="nova-marcacao-client-card-avatar-wrap mb-3">
                                            <img id="novaMarcacaoClientAvatar" src="" alt="" class="rounded-circle agenda-avatar-img d-none" width="80" height="80">
                                            <div id="novaMarcacaoClientAvatarFallback" class="nova-marcacao-avatar-fallback agenda-avatar-fallback rounded-circle d-flex align-items-center justify-content-center fw-semibold d-none" style="width:80px;height:80px;font-size:1.5rem;">—</div>
                                        </div>
                                        <strong id="novaMarcacaoClientSelectedName" class="d-block">—</strong>
                                        <span id="novaMarcacaoClientSelectedEmail" class="d-block small text-muted mb-3">—</span>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                            <button type="button" class="btn btn-light btn-sm" id="novaMarcacaoClientClear">Alterar</button>
                                            <a id="novaMarcacaoClientProfileLink" href="#" class="btn btn-light btn-sm" target="_blank" rel="noopener">Ver perfil</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="nova-marcacao-section nova-marcacao-agent-wrap text-center">
                                    <h6 class="nova-marcacao-section-title">Prestador(a) do serviço</h6>
                                    <a id="novaMarcacaoAgentLink" href="#" class="nova-marcacao-person nova-marcacao-agent-link text-decoration-none text-body d-inline-flex align-items-center gap-2 justify-content-center">
                                        <img id="novaMarcacaoAgentAvatar" src="" alt="" class="rounded-circle agenda-avatar-img flex-shrink-0" width="40" height="40" style="display: none;">
                                        <div class="flex-grow-1 min-w-0">
                                            <strong id="novaMarcacaoAgentName" class="d-block">—</strong>
                                        </div>
                                    </a>
                                </div>
                                <input type="hidden" id="novaMarcacaoObservacoes" name="description" value="">
                            </div>
                        </div>
                        <div class="col-lg-7 nova-marcacao-services-col" id="novaMarcacaoServicesCol">
                            <div class="nova-marcacao-services-col-main">
                            <h6 class="nova-marcacao-section-title mb-3 d-flex align-items-center" id="novaMarcacaoServicesTitle">
                                <span>Serviços</span>
                                <button type="button" class="btn btn-link btn-sm p-0 d-none" id="novaMarcacaoCancelAddServicesBtn">
                                    <i class="ph ph-arrow-left me-1"></i>Voltar
                                </button>
                            </h6>
                            <div id="novaMarcacaoServicesList" class="nova-marcacao-services-list">
                                <div class="text-muted small">A carregar serviços...</div>
                            </div>
                            <div id="novaMarcacaoServiceSelected" class="d-none">
                                <div id="novaMarcacaoSelectedServicesList"></div>
                                <div class="text-center mt-4">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="novaMarcacaoAddMoreServicesBtn">
                                        <i class="ph ph-plus me-1"></i>Adicionar serviços
                                    </button>
                                </div>
                            </div>
                            </div>
                            <div class="nova-marcacao-total-row d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="text-black fs-6 fw-bold">Total</span>
                                <span class="fw-semibold fs-6" id="novaMarcacaoTotalPrice">0,00 €</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer pt-3 pb-3 d-flex justify-content-end align-items-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary ms-2" id="novaMarcacaoSubmitBtn">Criar marcação</button>
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

<!-- Modal: Tempo pessoal (criar/editar) -->
<div class="modal fade" id="tempoPessoalModal" tabindex="-1" aria-labelledby="tempoPessoalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header pb-3 d-flex align-items-center justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold" id="tempoPessoalModalLabel">Tempo pessoal</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="tempoPessoalForm">
                <input type="hidden" id="tempoPessoalEventId" name="event_id">
                <input type="hidden" id="tempoPessoalStart" name="start_at">
                <input type="hidden" id="tempoPessoalEnd" name="end_at">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label d-block">Tipo de tempo pessoal</label>
                        <div class="tempo-pessoal-type-toggle-wrapper" role="group" id="tempoPessoalTypeToggleGroup">
                            @forelse($personalTimeTypes ?? [] as $pt)
                            <button type="button" class="tempo-pessoal-type-card btn border rounded-2 {{ $loop->first ? 'active' : '' }}" data-id="{{ $pt->id }}" data-duration="{{ $pt->duration }}" data-name="{{ $pt->name }}" title="{{ $pt->name }} · {{ $pt->formatted_duration }}">
                                <i class="ph {{ $pt->icon }} tempo-pessoal-type-card-icon"></i>
                                <span class="fw-semibold tempo-pessoal-type-card-name">{{ $pt->name }}</span>
                                <span class="text-muted small tempo-pessoal-type-card-duration">{{ $pt->formatted_duration }}</span>
                            </button>
                            @empty
                            <p class="text-muted small mb-0">Nenhum tipo configurado.</p>
                            @endforelse
                        </div>
                        <input type="hidden" id="tempoPessoalTipo" name="personal_time_type_id" value="{{ ($personalTimeTypes ?? collect())->first()?->id ?? '' }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="tempoPessoalDateToggle" class="form-label">Data</label>
                        <div class="dropdown w-100">
                            <span class="form-control dropdown-toggle d-flex align-items-center text-start" id="tempoPessoalDateToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer">—</span>
                            <div class="dropdown-menu dropdown-menu-start p-0 w-100" id="tempoPessoalDateDropdownMenu">
                                <div class="picker-inline-wrapper" id="tempoPessoalDatePickerWrap"></div>
                            </div>
                        </div>
                        <input type="hidden" id="tempoPessoalDateInput">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="tempoPessoalStartTimeToggle" class="form-label">Hora de início</label>
                            <div class="dropdown w-100">
                                <span class="form-control dropdown-toggle d-flex align-items-center text-start" id="tempoPessoalStartTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer">—</span>
                                <div class="dropdown-menu dropdown-menu-start p-0 w-100" id="tempoPessoalStartTimeDropdownMenu">
                                    <div class="tempo-pessoal-time-options agenda-time-options-scroll"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="tempoPessoalEndTimeToggle" class="form-label">Hora de fim</label>
                            <div class="dropdown w-100">
                                <span class="form-control dropdown-toggle d-flex align-items-center text-start" id="tempoPessoalEndTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer">—</span>
                                <div class="dropdown-menu dropdown-menu-start p-0 w-100" id="tempoPessoalEndTimeDropdownMenu">
                                    <div class="tempo-pessoal-end-time-options agenda-time-options-scroll"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="tempoPessoalMembro" class="form-label">Membro</label>
                        <select class="form-select" id="tempoPessoalMembro" name="user_id">
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
                    <div class="mb-3">
                        <label for="tempoPessoalDescricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="tempoPessoalDescricao" name="description" rows="2" placeholder="Observações..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="tempoPessoalDeleteBtn" style="display: none;">Eliminar</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="tempoPessoalSubmitBtn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cancelar marcação -->
<div class="modal fade" id="cancelMarcacaoModal" tabindex="-1" aria-labelledby="cancelMarcacaoModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h4 class="modal-title mb-0 fw-semibold" id="cancelMarcacaoModalLabel">Cancelar marcação</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancelMarcacaoEventId" value="">
                <div class="mb-4">
                    <label for="cancelMarcacaoQueAconteceu" class="form-label">O que aconteceu?</label>
                    <select class="form-select w-100" id="cancelMarcacaoQueAconteceu">
                        <option value="faltou">Faltou (não compareceu)</option>
                        <option value="cancelado">Cliente cancelou (avisou)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="cancelMarcacaoReason" class="form-label">Razão</label>
                    <select class="form-select w-100" id="cancelMarcacaoReason">
                        <option value="">— Selecionar razão (opcional) —</option>
                        <option value="O cliente não forneceu razão">O cliente não forneceu razão</option>
                        <option value="Marcação duplicada">Marcação duplicada</option>
                        <option value="Marcação feita por engano">Marcação feita por engano</option>
                        <option value="Cliente não disponível">Cliente não disponível</option>
                        <option value="outra">Outra razão</option>
                    </select>
                </div>
                <div id="cancelMarcacaoOutraWrap" class="mb-3 d-none">
                    <label for="cancelMarcacaoOutraTexto" class="form-label">Indique a razão</label>
                    <textarea class="form-control" id="cancelMarcacaoOutraTexto" rows="2" placeholder="Escreva a razão do cancelamento..."></textarea>
                </div>
                <div class="mb-3">
                    <label for="cancelMarcacaoRefund" class="form-label">Devolveu o valor da reserva?</label>
                    <select class="form-select w-100" id="cancelMarcacaoRefund">
                        <option value="">— Selecionar —</option>
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
                <div id="cancelMarcacaoAvisouWrap" class="mb-3 d-none">
                    <label for="cancelMarcacaoAvisouPrazo" class="form-label">Avisou dentro do prazo?</label>
                    <select class="form-select w-100" id="cancelMarcacaoAvisouPrazo">
                        <option value="">— Selecionar —</option>
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
                <div class="border rounded p-3 bg-light">
                    <h6 class="nova-marcacao-section-title mb-2">Detalhes do cancelamento</h6>
                    <div class="mb-0">
                        <span class="text-muted">Total da marcação:</span>
                        <strong id="cancelMarcacaoTotalPrice" class="ms-1">0,00 €</strong>
                    </div>
                    <p class="small text-muted mb-0 mt-2">De futuro, se houver condições de cancelamento iremos mostrar aqui.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-danger" id="cancelMarcacaoConfirmBtn">Cancelar marcação</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ver/Editar Evento (layout Nova Marcação, unificado) -->
<div class="modal fade" id="eventDetailEditModal" tabindex="-1" aria-labelledby="eventDetailEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header pb-3 d-flex align-items-center justify-content-between">
                <h4 class="modal-title mb-0 fw-semibold d-flex flex-wrap align-items-center gap-2">
                    <span class="dropdown">
                        <span class="event-detail-time-toggle dropdown-toggle" id="eventDetailDateToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                            <span id="eventDetailEditTitleDay">—</span>
                        </span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="eventDetailDateDropdownMenu">
                            <div class="picker-inline-wrapper" id="eventDetailDatePickerWrap"></div>
                        </div>
                    </span>
                    <span class="dropdown ms-3 me-3">
                        <span class="event-detail-time-toggle dropdown-toggle" id="eventDetailTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button">—</span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="eventDetailTimeDropdownMenu">
                            <div class="event-detail-time-options agenda-time-options-scroll">
                                <!-- Opções 00:00 - 23:30 geradas em JS -->
                            </div>
                        </div>
                    </span>
                    <span class="dropdown ms-1" id="eventDetailStatusDropdownWrap">
                        <span class="event-detail-time-toggle dropdown-toggle event-detail-status-toggle d-inline-flex align-items-center gap-1 text-muted" id="eventDetailStatusDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                            <span id="eventDetailStatusIcon"><i class="me-1 ph ph-clock"></i></span>
                            <span id="eventDetailStatusLabel">Agendado</span>
                        </span>
                        <div class="dropdown-menu dropdown-menu-start p-0" id="eventDetailStatusMenu">
                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="agendado"><i class="me-0 ph ph-clock"></i>Agendado</a>
                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="confirmado"><i class="me-0 ph ph-check"></i>Confirmado</a>
                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="chegou"><i class="me-0 ph ph-map-pin"></i>Chegou</a>
                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="iniciado"><i class="me-0 ph ph-play"></i>Iniciado</a>
                            <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2 text-danger" href="#" data-status="cancelar"><i class="me-0 ph ph-x-circle text-danger"></i>Cancelar</a>
                        </div>
                    </span>
                    <span class="ms-1 d-none event-detail-status-static text-success d-inline-flex align-items-center gap-1" id="eventDetailStatusStatic">
                        <span id="eventDetailStatusStaticIcon"><i class="me-1 ph ph-check-circle"></i></span>
                        <span id="eventDetailStatusStaticLabel">Concluído</span>
                    </span>
                </h4>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
            </div>
            <form id="eventDetailEditForm">
                <input type="hidden" id="eventDetailEditId" name="event_id">
                <input type="hidden" id="eventDetailEditUserId" name="user_id">
                <input type="hidden" id="eventDetailEditStart" name="start_at">
                <input type="hidden" id="eventDetailEditEnd" name="end_at">
                <div class="modal-body p-0">
                    <div class="row g-0">
                    <div class="col-lg-5">
                            <div class="nova-marcacao-sidebar">
                                <div class="nova-marcacao-section nova-marcacao-section-client" id="eventDetailClientSection">
                                    <div id="eventDetailClientAddWrap" class="nova-marcacao-client-add-wrap text-center d-none">
                                        <div class="nova-marcacao-client-add-icon mb-3"><i class="ph ph-user-circle-plus"></i></div>
                                        <strong id="eventDetailAddClientBtn" class="d-block">Pesquisar cliente</strong>
                                    </div>
                                    <div id="eventDetailClientSearchWrap" class="mb-0 d-none">
                                        <div class="d-flex gap-2 align-items-center mb-3">
                                            <input type="text" id="eventDetailClientSearch" class="form-control form-control-sm flex-grow-1" placeholder="Nome, telemóvel, email..." autocomplete="off">
                                            <button type="button" class="btn btn-light btn-sm d-none" id="eventDetailClientCancelBtn">Cancelar</button>
                                        </div>
                                        <div class="text-center mb-2">
                                            <a href="#" class="nova-marcacao-create-client-link" id="eventDetailCreateClientBtn">
                                                <i class="ph ph-plus"></i> criar novo cliente
                                            </a>
                                        </div>
                                        <div id="eventDetailClientResults" class="nova-marcacao-client-results mb-0"></div>
                                    </div>
                                    <div id="eventDetailClientSelected" class="nova-marcacao-client-card d-none text-center">
                                        <div class="nova-marcacao-client-card-avatar-wrap mb-3">
                                            <img id="eventDetailClientAvatar" src="" alt="" class="rounded-circle agenda-avatar-img d-none" width="80" height="80">
                                            <div id="eventDetailClientAvatarFallback" class="nova-marcacao-avatar-fallback agenda-avatar-fallback rounded-circle d-flex align-items-center justify-content-center fw-semibold d-none" style="width:80px;height:80px;font-size:1.5rem;">—</div>
                                        </div>
                                        <strong id="eventDetailClientSelectedName" class="d-block">—</strong>
                                        <span id="eventDetailClientSelectedEmail" class="d-block small text-muted mb-3">—</span>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                            <button type="button" class="btn btn-light btn-sm" id="eventDetailClientClear">Alterar</button>
                                            <a id="eventDetailClientProfileLink" href="#" class="btn btn-light btn-sm" target="_blank" rel="noopener">Ver perfil</a>
                                        </div>
                                    </div>
                                    <div id="eventDetailVisitLeadBlock" class="d-none"></div>
                                </div>
                                <div class="nova-marcacao-section nova-marcacao-agent-wrap text-center">
                                    <h6 class="nova-marcacao-section-title mb-2">Prestador(a) do serviço</h6>
                                    <a id="eventDetailAgentLink" href="#" class="nova-marcacao-person nova-marcacao-agent-link text-decoration-none text-body d-inline-flex align-items-center gap-2 justify-content-center">
                                        <img id="eventDetailAgentAvatar" src="" alt="" class="rounded-circle agenda-avatar-img flex-shrink-0" width="40" height="40">
                                        <div class="flex-grow-1 min-w-0">
                                            <strong id="eventDetailAgentName" class="d-block">—</strong>
                                        </div>
                                    </a>
                                </div>
                                <input type="hidden" id="eventDetailStatus" name="status" value="agendado">
                                <input type="hidden" id="eventDetailObservacoes" name="description" value="">
                            </div>
                        </div>
                        <div class="col-lg-7 nova-marcacao-services-col" id="eventDetailServicesCol">
                            <div class="nova-marcacao-services-col-main">
                            <h6 class="nova-marcacao-section-title mb-3 d-flex align-items-center">
                                <span>Serviços</span>
                                <button type="button" class="btn btn-link btn-sm p-0 d-none" id="eventDetailCancelAddServicesBtn">
                                    <i class="ph ph-arrow-left me-1"></i>Voltar
                                </button>
                            </h6>
                            <div id="eventDetailServicesList" class="nova-marcacao-services-list">
                                <div class="text-muted small">A carregar serviços...</div>
                            </div>
                            <div id="eventDetailServiceSelected" class="d-none">
                                <div id="eventDetailSelectedServicesList"></div>
                                <div class="text-center mt-4">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="eventDetailAddMoreServicesBtn">
                                        <i class="ph ph-plus me-1"></i>Adicionar serviços
                                    </button>
                                </div>
                            </div>
                            </div>
                            <div class="nova-marcacao-total-row d-flex justify-content-between align-items-center pt-3 border-top">
                                <span class="text-black fs-6 fw-bold">Total</span>
                                <span class="fw-semibold fs-6" id="eventDetailTotalPrice">0,00 €</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer pt-3 pb-3 d-flex justify-content-end align-items-center">
                <button type="submit" class="btn btn-primary" id="eventDetailSaveBtn">Guardar</button>
                    <div class="d-flex align-items-center gap-3" id="eventDetailPaymentWrap">
                        <button type="button" class="btn btn-success d-none" id="eventDetailPaymentBtn">Pagamento</button>
                        <a href="#" class="btn btn-outline-primary d-none" id="eventDetailVerFaturaLink" target="_blank" rel="noopener">Ver fatura</a>
                        <button type="button" class="btn btn-outline-secondary d-none" id="eventDetailReverterFaturaBtn">Reverter fatura</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Pagamento (abre por cima do modal da marcação) -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h5 class="modal-title mb-0 fw-semibold" id="paymentModalLabel">Pagamento</h5>
                <button type="button" class="btn-close" id="paymentModalCloseBtn" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label d-block">Meio de pagamento</label>
                    <div class="tempo-pessoal-type-toggle-wrapper" role="group" id="paymentMethodToggleGroup">
                        <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="dinheiro">
                            <i class="ph ph-money tempo-pessoal-type-card-icon"></i>
                            <span class="fw-semibold tempo-pessoal-type-card-name">Dinheiro</span>
                        </button>
                        <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="cartao">
                            <i class="ph ph-credit-card tempo-pessoal-type-card-icon"></i>
                            <span class="fw-semibold tempo-pessoal-type-card-name">Cartão</span>
                        </button>
                        <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="mbway">
                            <i class="ph ph-device-mobile tempo-pessoal-type-card-icon"></i>
                            <span class="fw-semibold tempo-pessoal-type-card-name">MB Way</span>
                        </button>
                        <button type="button" class="tempo-pessoal-type-card btn border rounded-2 payment-method-card" data-method="multibanco">
                            <i class="ph ph-bank tempo-pessoal-type-card-icon"></i>
                            <span class="fw-semibold tempo-pessoal-type-card-name">Multibanco</span>
                        </button>
                    </div>
                    <input type="hidden" id="paymentMethodValue" value="">
                </div>
                <div class="mb-3">
                    <label for="paymentGorjeta" class="form-label">Gorjeta (€)</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="paymentGorjeta" value="0" placeholder="0,00">
                </div>
                <div class="border-top pt-2">
                    <div class="d-flex justify-content-between small text-muted"><span>Subtotal</span><span id="paymentSubtotalDisplay">0,00 €</span></div>
                    <div class="d-flex justify-content-between small text-muted d-none" id="paymentGorjetaLine"><span>Gorjeta</span><span id="paymentGorjetaDisplay">0,00 €</span></div>
                    <div class="d-flex justify-content-between fw-semibold mt-1"><span>Total</span><span id="paymentTotalDisplay">0,00 €</span></div>
                </div>
            </div>
            <div class="modal-footer pt-3 pb-3">
                <button type="button" class="btn btn-light" id="paymentCancelBtn">Cancelar</button>
                <button type="button" class="btn btn-primary" id="paymentConfirmBtn">Confirmar e faturar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/pt.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js"></script>

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
    $usersForConsultant = ($users ?? collect())->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
@endphp
<script>
window.AGENDA_CONFIG = {
    csrf: @json(csrf_token()),
    eventsUrl: @json(route('agenda.events')),
    resourcesUrl: @json(route('agenda.resources')),
    clientesBaseUrl: @json(url('clientes')),
    currentUserIsAdmin: @json(auth()->user()->role === \App\Models\User::ROLE_ADMIN),
    authId: @json(auth()->id()),
    authName: @json(auth()->user()->name ?? 'Eu'),
    authEmail: @json(auth()->user()->email ?? ''),
    agendaMembersServicesUrl: @json(url('agenda/members')),
    agendaClientsUrl: @json(url('agenda/clients')),
    agendaEquipaBaseUrl: @json(url('equipa')),
    urlEvents: @json(url('agenda/events')),
    urlEventsStore: @json(route('agenda.events.store')),
    agendaCheckoutStoreUrl: @json(route('agenda.checkout.store')),
    salesRevertUrl: @json(url('sales')),
    urlOpportunities: @json(url('opportunities')),
    urlLeads: @json(url('leads')),
    agendaAgentInfo: @json($agentInfoMap->all()),
    usersForConsultant: @json($usersForConsultant)
};
</script>
<script src="{{ asset('template/js/agenda.js') }}?v={{ file_exists(public_path('template/js/agenda.js')) ? filemtime(public_path('template/js/agenda.js')) : time() }}"></script>

@endsection