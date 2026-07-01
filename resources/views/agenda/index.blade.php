@extends('partials.layouts.main')
@section('title', 'Agenda | Beauty CRM')
@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/css/intlTelInput.css">
    <link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ static_asset('template/css/agenda.css') }}">
@endsection
@section('content')
<div class="row g-0">
    <div class="col-12 p-0">
        <div class="card border-0 shadow-none">
            <div class="card-body p-0">
                <!-- Âncora invisível para o Flatpickr ao clicar no título da data na toolbar -->
                <input type="text" id="agendaToolbarDatePicker" class="visually-hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<!-- Nova marcação: offcanvas lateral -->
<div class="offcanvas offcanvas-end agenda-marcacao-test-offcanvas" tabindex="-1" id="agendaMarcacaoTestOffcanvas" aria-labelledby="agendaMarcacaoTestOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom align-items-start">
        <div>
            <h5 class="offcanvas-title fw-semibold mb-0" id="agendaMarcacaoTestOffcanvasLabel">Nova marcação</h5>
        </div>
        <button type="button" class="btn-close mt-0" data-bs-dismiss="offcanvas" data-agenda-oc-close aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <form id="agendaMarcacaoTestForm" class="agenda-oc-test-form" autocomplete="off">
            <div class="agenda-oc-field" style="order:1">
                <div id="agendaOcClientNotSelectedWrap">
                    <ul class="nav nav-pills agenda-oc-client-tabs flex-wrap gap-2 mb-2" id="agendaOcClientTabs" role="tablist">
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link active" id="agendaOcTabExistingBtn" data-bs-toggle="tab" data-bs-target="#agendaOcTabExisting" type="button" role="tab" aria-controls="agendaOcTabExisting" aria-selected="true">
                                <span class="d-inline-flex align-items-center justify-content-center gap-2">
                                    <i class="ph ph-magnifying-glass flex-shrink-0" aria-hidden="true"></i>
                                    <span>Cliente existente</span>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="agendaOcTabNewBtn" data-bs-toggle="tab" data-bs-target="#agendaOcTabNew" type="button" role="tab" aria-controls="agendaOcTabNew" aria-selected="false">
                                <span class="d-inline-flex align-items-center justify-content-center gap-2">
                                    <i class="ph ph-user-plus flex-shrink-0" aria-hidden="true"></i>
                                    <span>Novo cliente</span>
                                </span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="agendaOcClientTabContent">
                        <div class="tab-pane fade show active" id="agendaOcTabExisting" role="tabpanel" aria-labelledby="agendaOcTabExistingBtn" tabindex="0">
                            <div id="agendaOcClientSearchWrap">
                                <select id="agendaOcClient" class="form-select form-select-sm" data-placeholder="Pesquisar cliente" aria-label="Pesquisar cliente">
                                    <option value="">A carregar…</option>
                                </select>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="agendaOcTabNew" role="tabpanel" aria-labelledby="agendaOcTabNewBtn" tabindex="0">
                            <div class="mb-2">
                                <label class="form-label small mb-1" for="agendaOcNewClientName">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="agendaOcNewClientName" name="agenda_oc_new_client_name" autocomplete="name" placeholder="Nome completo">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-1" for="agendaOcNewClientPhone">Telemóvel <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="agendaOcNewClientPhone" autocomplete="tel" placeholder="Número de telemóvel">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small mb-1" for="agendaOcNewClientEmail">Email</label>
                                <input type="email" class="form-control form-control-sm" id="agendaOcNewClientEmail" name="agenda_oc_new_client_email" autocomplete="email" placeholder="Opcional">
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="agendaOcNewClientSubmit">Guardar cliente</button>
                        </div>
                    </div>
                </div>
                <div id="agendaOcClientSelectedCard" class="agenda-oc-client-selected-card d-none mt-1">
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-shrink-0 agenda-oc-client-col-avatar">
                            <img id="agendaOcClientAvatar" src="" alt="" class="rounded-circle agenda-avatar-img d-none">
                            <div id="agendaOcClientAvatarFallback" class="agenda-avatar-fallback rounded-circle d-flex align-items-center justify-content-center fw-semibold d-none">…</div>
                        </div>
                        <div class="flex-grow-1 min-w-0 agenda-oc-client-col-text">
                            <strong id="agendaOcClientSelectedName" class="d-block text-truncate">…</strong>
                            <span id="agendaOcClientSelectedPhone" class="d-block small text-muted">…</span>
                            <div class="agenda-oc-client-nif-row position-relative">
                                <span id="agendaOcClientNifDisplayWrap" class="d-inline-flex align-items-center gap-1 small text-muted agenda-oc-client-nif-display">
                                    <span id="agendaOcClientSelectedNif">Sem NIF</span>
                                    <button type="button" class="btn btn-link p-0 lh-1 text-body-secondary text-decoration-none agenda-oc-client-edit-btn agenda-oc-client-nif-edit-btn" id="agendaOcClientNifEditBtn" title="Editar NIF" aria-label="Editar NIF">
                                        <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                    </button>
                                </span>
                                <span id="agendaOcClientNifInputWrap" class="d-none agenda-oc-client-nif-input-wrap">
                                    <div class="d-flex align-items-center gap-1">
                                        <input type="text" id="agendaOcClientNifInput" class="form-control form-control-sm agenda-oc-client-nif-input" maxlength="9" inputmode="numeric" pattern="[0-9]*" placeholder="NIF (9 dígitos)">
                                        <button type="button" class="btn btn-sm btn-primary px-2 py-1" id="agendaOcClientNifSaveBtn">OK</button>
                                        <button type="button" class="btn btn-link p-1 lh-1 text-body-secondary text-decoration-none" id="agendaOcClientNifCancelBtn" title="Cancelar edição de NIF" aria-label="Cancelar edição de NIF">
                                            <i class="ph ph-x" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </span>
                            </div>
                        </div>
                        <div class="flex-shrink-0 d-inline-flex agenda-oc-client-col-actions">
                            <a id="agendaOcClientProfileLink" href="#" class="btn btn-link p-1 lh-1 text-body-secondary agenda-oc-client-profile-btn d-none text-decoration-none" title="Ver perfil" aria-label="Ver perfil">
                                <i class="ph ph-eye" aria-hidden="true"></i>
                            </a>
                            <button type="button" class="btn btn-link p-1 lh-1 text-body-secondary agenda-oc-client-edit-btn" id="agendaOcClientEditBtn" title="Trocar cliente" aria-label="Trocar cliente">
                                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="agenda-oc-field" style="order:2">
                <label class="form-label fw-semibold text-dark mb-1" for="agendaOcMember">Prestador(a) do serviço <span class="text-danger">*</span></label>
                <select id="agendaOcMember" class="form-select form-select-sm">
                    <option value="">Selecionar</option>
                </select>
            </div>
            <div class="agenda-oc-field" style="order:3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1 agenda-oc-services-head">
                    <label class="form-label fw-semibold text-dark mb-0" for="agendaOcService">Serviços <span class="text-danger">*</span></label>
                    <div id="agendaOcAddMoreServicesWrap" class="d-none flex-shrink-0">
                        <button type="button" id="agendaOcAddMoreServicesBtn" class="btn btn-outline-primary agenda-oc-add-services-btn rounded-pill d-inline-flex align-items-center gap-1">
                            <i class="ph ph-plus" aria-hidden="true"></i>
                            <span>Adicionar serviços</span>
                        </button>
                    </div>
                </div>
                <div id="agendaOcServiceSelectWrap">
                    <select id="agendaOcService" class="form-select form-select-sm" disabled>
                        <option value="">Escolha primeiro o prestador(a)</option>
                    </select>
                </div>
                <div id="agendaOcSelectedServicesList" class="mt-2 d-none"></div>
            </div>
            <div class="agenda-oc-field" style="order:4">
                <label class="form-label fw-semibold text-dark mb-1" for="agendaOcObs">Notas da Marcação</label>
                <textarea class="form-control form-control-sm" id="agendaOcObs" name="description" rows="3" placeholder="Escreva uma nota sobre esta marcação"></textarea>
            </div>
            <div class="agenda-oc-field" style="order:5">
                <div class="mb-2 d-none" id="agendaOcHorarioAvisoWrap">
                    <div class="alert alert-warning mb-0 py-2 small" id="agendaOcHorarioAviso" role="alert">
                        <i class="ph ph-warning-circle me-1"></i>
                        Horário fora do período habitual da loja ({{ $storeHoursLabel ?? '09:00–20:00' }}) ou do membro. Pode guardar na mesma, se for excecional.
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="form-label fw-semibold text-dark mb-1" for="agendaOcDate">Data <span class="text-danger">*</span></label>
                        <input type="text" id="agendaOcDate" class="form-control" placeholder="Selecionar data">
                    </div>
                    <div class="col-5">
                        <label class="form-label fw-semibold text-dark mb-1" for="agendaOcTime">Hora <span class="text-danger">*</span></label>
                        <select id="agendaOcTime" class="form-select"></select>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="agenda-marcacao-test-offcanvas-footer border-top d-flex flex-wrap gap-2 justify-content-end">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="offcanvas" data-agenda-oc-close>Cancelar</button>
        <button type="submit" class="btn btn-primary btn-sm" id="agendaOcSubmit" form="agendaMarcacaoTestForm">Criar marcação</button>
    </div>
</div>

<!-- Quick menu popup (ao clicar numa célula) - mesmo aspeto do quick access da navbar -->
<div id="agendaQuickMenu" role="menu" aria-label="Opções"></div>

<!-- Quickview do evento (ao passar o rato por cima do evento) -->
<div id="agendaEventQuickview" role="tooltip" aria-label="Detalhe do evento" class="agenda-event-quickview"></div>

<!-- Modal: confirmar arrastar/redimensionar marcação (avisar cliente) -->
<div class="modal fade" id="agendaDragConfirmModal" tabindex="-1" aria-labelledby="agendaDragConfirmModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h4 class="modal-title mb-0 fw-semibold" id="agendaDragConfirmModalLabel">Confirmar alteração</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="agendaDragConfirmNotify">
                    <label class="form-check-label" for="agendaDragConfirmNotify">Avisar cliente da mudança da marcação</label>
                </div>
                <p class="small text-muted mb-2">
                    Enviar uma mensagem a <strong id="agendaDragConfirmClientName">…</strong> a avisar que a marcação foi alterada.
                </p>
                <p class="small text-warning mb-0 d-none" id="agendaDragConfirmNoEmail">Este cliente não tem email válido; não será enviada mensagem.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="agendaDragConfirmCancel">Cancelar</button>
                <button type="button" class="btn btn-primary" id="agendaDragConfirmSubmit">Atualizar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: alterações não guardadas (fechar offcanvas de editar marcação) -->
<div class="modal fade" id="eventDetailUnsavedChangesModal" tabindex="-1" aria-labelledby="eventDetailUnsavedChangesModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h4 class="modal-title mb-0 fw-semibold" id="eventDetailUnsavedChangesModalLabel">Alterações não guardadas</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Atenção, existem alterações não guardadas. Deseja continuar?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" id="eventDetailUnsavedDiscardBtn">Continuar sem guardar</button>
                <button type="button" class="btn btn-primary" id="eventDetailUnsavedSaveBtn">Guardar alterações</button>
            </div>
        </div>
    </div>
</div>

<!-- Quick menu: editar duração/preço do serviço (offcanvas detalhe da marcação) -->
<div id="novaMarcacaoEditServiceQuickMenu" role="dialog" aria-label="Alterar opções do serviço"></div>
<!-- Quick menu: adicionar extra (offcanvas detalhe da marcação) -->
<div id="eventDetailAddExtrasQuickMenu" role="dialog" aria-label="Adicionar extra" class="nova-marcacao-quick-menu-extras" style="position: absolute;"></div>

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
                    <div class="alert alert-warning d-none" id="tempoPessoalHorarioAviso" role="alert">
                        <i class="ph ph-warning-circle me-1"></i>
                        Horário fora do período habitual da loja ({{ $storeHoursLabel ?? '09:00–20:00' }}) ou do membro. Pode guardar na mesma, se for excecional.
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Tipo de tempo pessoal</label>
                        <div class="tempo-pessoal-type-toggle-wrapper" role="group" id="tempoPessoalTypeToggleGroup">
                            @foreach($personalTimeTypes ?? [] as $pt)
                            <button type="button" class="tempo-pessoal-type-card btn border rounded-2 {{ $loop->first ? 'active' : '' }}" data-id="{{ $pt->id }}" data-duration="{{ $pt->duration }}" data-name="{{ $pt->name }}" title="{{ $pt->name }} · {{ $pt->formatted_duration }}">
                                <i class="ph {{ $pt->icon }} tempo-pessoal-type-card-icon"></i>
                                <span class="fw-semibold tempo-pessoal-type-card-name">{{ $pt->name }}</span>
                                <span class="text-muted small tempo-pessoal-type-card-duration">{{ $pt->formatted_duration }}</span>
                            </button>
                            @endforeach
                            <button type="button" class="tempo-pessoal-type-card btn border rounded-2 {{ empty($personalTimeTypes) || count($personalTimeTypes) === 0 ? 'active' : '' }}" data-id="" data-duration="60" data-name="Outro" data-is-custom="1" title="Outro · 1h">
                                <i class="ph ph-pencil tempo-pessoal-type-card-icon"></i>
                                <span class="fw-semibold tempo-pessoal-type-card-name">Outro</span>
                                <span class="text-muted small tempo-pessoal-type-card-duration">1h</span>
                            </button>
                        </div>
                        <input type="hidden" id="tempoPessoalTipo" name="personal_time_type_id" value="{{ ($personalTimeTypes ?? collect())->first()?->id ?? '' }}">
                    </div>
                    <div class="mb-3 d-none" id="tempoPessoalTituloWrap">
                        <label for="tempoPessoalTitulo" class="form-label">Título do Tempo Pessoal</label>
                        <input type="text" class="form-control" id="tempoPessoalTitulo" name="title" maxlength="255" placeholder="Escreva o título...">
                    </div>
                    <div class="mb-3">
                        <label for="tempoPessoalDateToggle" class="form-label">Data</label>
                        <div class="dropdown w-100">
                            <span class="form-control dropdown-toggle d-flex align-items-center text-start" id="tempoPessoalDateToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer">…</span>
                            <div class="dropdown-menu dropdown-menu-start p-0" id="tempoPessoalDateDropdownMenu">
                                <div class="picker-inline-wrapper" id="tempoPessoalDatePickerWrap"></div>
                            </div>
                        </div>
                        <input type="hidden" id="tempoPessoalDateInput">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="tempoPessoalStartTimeToggle" class="form-label">Hora de início</label>
                            <div class="dropdown w-100">
                                <span class="form-control dropdown-toggle d-flex align-items-center text-start" id="tempoPessoalStartTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer">…</span>
                                <div class="dropdown-menu dropdown-menu-start p-0 w-100" id="tempoPessoalStartTimeDropdownMenu">
                                    <div class="tempo-pessoal-time-options agenda-time-options-scroll"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="tempoPessoalEndTimeToggle" class="form-label">Hora de fim</label>
                            <div class="dropdown w-100">
                                <span class="form-control dropdown-toggle d-flex align-items-center text-start" id="tempoPessoalEndTimeToggle" data-bs-toggle="dropdown" aria-expanded="false" role="button" style="cursor: pointer">…</span>
                                <div class="dropdown-menu dropdown-menu-start p-0 w-100" id="tempoPessoalEndTimeDropdownMenu">
                                    <div class="tempo-pessoal-end-time-options agenda-time-options-scroll"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="tempoPessoalMembro" class="form-label">Membro</label>
                        <select class="form-select" id="tempoPessoalMembro" name="user_id">
                            @if(auth()->user()->isAdmin() || auth()->user()->isRececao())
                                <option value="">Selecionar membro</option>
                                @foreach($users as $u)
                                    @if($u->id !== auth()->id() && $u->role !== \App\Models\User::ROLE_ADMIN)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endif
                                @endforeach
                            @else
                                <option value="{{ auth()->id() }}">{{ auth()->user()->name }}</option>
                            @endif
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
                        <option value="">Selecionar razão (opcional)</option>
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
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="cancelMarcacaoNotifyClient">
                    <label class="form-check-label" for="cancelMarcacaoNotifyClient">Avisar cliente do cancelamento</label>
                </div>
                <div class="border rounded p-3 bg-light">
                    <h6 class="nova-marcacao-section-title mb-2">Política de cancelamento</h6>
                    <div class="mb-0">
                        <span class="text-muted">Total da marcação:</span>
                        <strong id="cancelMarcacaoTotalPrice" class="ms-1">0,00 €</strong>
                    </div>
                    <p class="small text-muted mb-0" id="cancelMarcacaoPolicyLoading">A calcular…</p><p class="small mb-0 d-none" id="cancelMarcacaoPolicyStatus"></p><p class="small text-muted mb-0 mt-2 d-none" id="cancelMarcacaoPolicyCredit"></p><p class="small text-danger mb-0 mt-2 d-none" id="cancelMarcacaoPolicyForfeit"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Não cancelar</button>
                <button type="button" class="btn btn-danger" id="cancelMarcacaoConfirmBtn">Cancelar marcação</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Anular fatura (pagamento em loja) -->
<div class="modal fade" id="revertSaleModal" tabindex="-1" aria-labelledby="revertSaleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header pb-3">
                <h4 class="modal-title mb-0 fw-semibold" id="revertSaleModalLabel">Anular fatura</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="revertSaleId" value="">
                <p class="text-muted mb-3" id="revertSaleImpactText">Será gerada uma nota de crédito. A fatura de pré-pagamento mantém-se. A marcação continua paga; depois pode emitir uma nova fatura final (ex.: corrigir NIF).</p>
                <div class="mb-0">
                    <label for="revertSaleReason" class="form-label">Razão da anulação</label>
                    <textarea class="form-control" id="revertSaleReason" rows="3" maxlength="1000" placeholder="Ex.: cliente pediu NIF na fatura final, erro no documento, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="revertSaleConfirmBtn">Anular fatura</button>
            </div>
        </div>
    </div>
</div>

<!-- Offcanvas: Ver/Editar marcação (estrutura igual à nova marcação: cliente, serviços, profissional, data, notas) -->
<div class="offcanvas offcanvas-end agenda-marcacao-test-offcanvas" tabindex="-1" id="eventDetailEditModal" aria-labelledby="eventDetailEditOffcanvasLabel" data-bs-scroll="true">
    <div class="offcanvas-header border-bottom d-flex align-items-center gap-2 py-3">
        <div class="d-flex align-items-center flex-grow-1 min-w-0 gap-3">
            <h5 class="offcanvas-title fw-semibold mb-0 flex-shrink-0" id="eventDetailEditOffcanvasLabel">Marcação</h5>
            <div class="d-flex align-items-center min-w-0 flex-shrink-0">
                <span class="dropdown" id="eventDetailStatusDropdownWrap">
                    <span class="event-detail-time-toggle dropdown-toggle event-detail-status-toggle d-inline-flex align-items-center text-muted small text-decoration-none" id="eventDetailStatusDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                        <span id="eventDetailStatusIcon" class="event-detail-status-trigger-icon" aria-hidden="true"><i class="ri-time-fill agenda-status-icon-agendado"></i></span>
                        <span id="eventDetailStatusLabel">Agendado</span>
                    </span>
                    <div class="dropdown-menu dropdown-menu-start p-0" id="eventDetailStatusMenu">
                        <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="agendado"><i class="me-0 ri-time-fill agenda-status-icon-agendado"></i>Agendado</a>
                        <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="notificado"><i class="me-0 ri-notification-3-fill agenda-status-icon-notificado"></i>Notificado</a>
                        <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="confirmado"><i class="me-0 ri-notification-3-fill agenda-status-icon-confirmado"></i>Confirmado</a>
                        <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="chegou"><i class="me-0 ri-map-pin-fill agenda-status-icon-chegou"></i>Chegou</a>
                        <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2" href="#" data-status="iniciado"><i class="me-0 ri-play-fill agenda-status-icon-iniciado"></i>Iniciado</a>
                        <a class="dropdown-item event-detail-status-opt d-flex align-items-center gap-2 text-danger" href="#" data-status="cancelar"><i class="me-0 ri-close-circle-fill text-danger"></i>Cancelar</a>
                    </div>
                </span>
                <span class="d-none event-detail-status-static d-inline-flex align-items-center small text-muted" id="eventDetailStatusStatic">
                    <span id="eventDetailStatusStaticIcon" class="event-detail-status-trigger-icon" aria-hidden="true"><i class="ri-checkbox-circle-fill"></i></span>
                    <span id="eventDetailStatusStaticLabel">Concluído</span>
                </span>
            </div>
        </div>
        <button type="submit" class="btn btn-light btn-sm border flex-shrink-0 d-inline-flex align-items-center gap-2 event-detail-header-save-btn" id="eventDetailSaveBtn" form="eventDetailEditForm" aria-label="Guardar alterações" title="Guardar alterações">
            <i class="ph ph-floppy-disk" aria-hidden="true"></i>
            <span class="event-detail-header-save-label event-detail-header-save-label--long">Guardar alterações</span>
            <span class="event-detail-header-save-label event-detail-header-save-label--short">Guardar</span>
        </button>
        <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div id="eventDetailEditPanel" class="offcanvas-body">
        <form id="eventDetailEditForm" class="agenda-oc-test-form" autocomplete="off">
            <input type="hidden" id="eventDetailEditId" name="event_id">
            <input type="hidden" id="eventDetailEditUserId" name="user_id">
            <input type="hidden" id="eventDetailEditStart" name="start_at">
            <input type="hidden" id="eventDetailEditEnd" name="end_at">
            <input type="hidden" id="eventDetailStatus" name="status" value="agendado">
            <div id="eventDetailVisitLeadBlock" class="d-none mb-3"></div>
            <div id="eventDetailOcMarcacaoSection">
                <div class="agenda-oc-field" style="order:1">
                    <div id="eventDetailOcClientNotSelectedWrap">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <ul class="nav nav-pills agenda-oc-client-tabs flex-wrap gap-2 mb-0" id="eventDetailOcClientTabs" role="tablist">
                                <li class="nav-item flex-shrink-0" role="presentation">
                                    <button class="nav-link active" id="eventDetailOcTabExistingBtn" data-bs-toggle="tab" data-bs-target="#eventDetailOcTabExisting" type="button" role="tab" aria-controls="eventDetailOcTabExisting" aria-selected="true">
                                        <span class="d-inline-flex align-items-center justify-content-center gap-2">
                                            <i class="ph ph-magnifying-glass flex-shrink-0" aria-hidden="true"></i>
                                            <span>Cliente existente</span>
                                        </span>
                                    </button>
                                </li>
                                <li class="nav-item flex-shrink-0" role="presentation">
                                    <button class="nav-link" id="eventDetailOcTabNewBtn" data-bs-toggle="tab" data-bs-target="#eventDetailOcTabNew" type="button" role="tab" aria-controls="eventDetailOcTabNew" aria-selected="false">
                                        <span class="d-inline-flex align-items-center justify-content-center gap-2">
                                            <i class="ph ph-user-plus flex-shrink-0" aria-hidden="true"></i>
                                            <span>Novo cliente</span>
                                        </span>
                                    </button>
                                </li>
                            </ul>
                            <button type="button" class="btn btn-link p-1 pt-2 lh-1 text-body-secondary text-decoration-none agenda-oc-client-edit-btn d-none" id="eventDetailOcClientCancelEditBtn" title="Cancelar alteração de cliente" aria-label="Cancelar alteração de cliente">
                                <i class="ph ph-x" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="tab-content" id="eventDetailOcClientTabContent">
                            <div class="tab-pane fade show active" id="eventDetailOcTabExisting" role="tabpanel" aria-labelledby="eventDetailOcTabExistingBtn" tabindex="0">
                                <div id="eventDetailOcClientSearchWrap">
                                    <select id="eventDetailOcClient" class="form-select form-select-sm" data-placeholder="Pesquisar cliente" aria-label="Pesquisar cliente">
                                        <option value="">A carregar…</option>
                                    </select>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="eventDetailOcTabNew" role="tabpanel" aria-labelledby="eventDetailOcTabNewBtn" tabindex="0">
                                <div class="mb-2">
                                    <label class="form-label small mb-1" for="eventDetailOcNewClientName">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="eventDetailOcNewClientName" name="event_detail_oc_new_client_name" autocomplete="name" placeholder="Nome completo">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1" for="eventDetailOcNewClientPhone">Telemóvel <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="eventDetailOcNewClientPhone" autocomplete="tel" placeholder="Número de telemóvel">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small mb-1" for="eventDetailOcNewClientEmail">Email</label>
                                    <input type="email" class="form-control form-control-sm" id="eventDetailOcNewClientEmail" name="event_detail_oc_new_client_email" autocomplete="email" placeholder="Opcional">
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" id="eventDetailOcNewClientSubmit">Guardar cliente</button>
                            </div>
                        </div>
                    </div>
                    <div id="eventDetailOcClientSelectedCard" class="agenda-oc-client-selected-card d-none mt-1">
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-shrink-0 agenda-oc-client-col-avatar">
                                <img id="eventDetailOcClientAvatar" src="" alt="" class="rounded-circle agenda-avatar-img d-none">
                                <div id="eventDetailOcClientAvatarFallback" class="agenda-avatar-fallback rounded-circle d-flex align-items-center justify-content-center fw-semibold d-none">…</div>
                            </div>
                            <div class="flex-grow-1 min-w-0 agenda-oc-client-col-text">
                                <strong id="eventDetailOcClientSelectedName" class="d-block text-truncate">…</strong>
                                <span id="eventDetailOcClientSelectedPhone" class="d-block small text-muted">…</span>
                                <div class="agenda-oc-client-nif-row position-relative">
                                    <span id="eventDetailOcClientNifDisplayWrap" class="d-inline-flex align-items-center gap-1 small text-muted agenda-oc-client-nif-display">
                                        <span id="eventDetailOcClientSelectedNif">Sem NIF</span>
                                        <button type="button" class="btn btn-link p-0 lh-1 text-body-secondary text-decoration-none agenda-oc-client-edit-btn agenda-oc-client-nif-edit-btn" id="eventDetailOcClientNifEditBtn" title="Editar NIF" aria-label="Editar NIF">
                                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                        </button>
                                    </span>
                                    <span id="eventDetailOcClientNifInputWrap" class="d-none agenda-oc-client-nif-input-wrap">
                                        <div class="d-flex align-items-center gap-1">
                                            <input type="text" id="eventDetailOcClientNifInput" class="form-control form-control-sm agenda-oc-client-nif-input" maxlength="9" inputmode="numeric" pattern="[0-9]*" placeholder="NIF (9 dígitos)">
                                            <button type="button" class="btn btn-sm btn-primary px-2 py-1" id="eventDetailOcClientNifSaveBtn">OK</button>
                                            <button type="button" class="btn btn-link p-1 lh-1 text-body-secondary text-decoration-none" id="eventDetailOcClientNifCancelBtn" title="Cancelar edição de NIF" aria-label="Cancelar edição de NIF">
                                                <i class="ph ph-x" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 d-inline-flex agenda-oc-client-col-actions">
                                <a id="eventDetailOcClientProfileLink" href="#" class="btn btn-link p-1 lh-1 text-body-secondary agenda-oc-client-profile-btn d-none text-decoration-none" title="Ver perfil" aria-label="Ver perfil">
                                    <i class="ph ph-eye" aria-hidden="true"></i>
                                </a>
                                <button type="button" class="btn btn-link p-1 lh-1 text-body-secondary agenda-oc-client-edit-btn" id="eventDetailOcClientEditBtn" title="Trocar cliente" aria-label="Trocar cliente">
                                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="eventDetailOcClientBirthdayBanner" class="agenda-client-birthday-banner d-none mt-2" role="status" aria-live="polite">
                        <span class="agenda-client-birthday-banner__icon" aria-hidden="true"><i class="ri-gift-2-fill"></i></span>
                        <span id="eventDetailOcClientBirthdayText" class="agenda-client-birthday-banner__text"></span>
                    </div>
                </div>
                <div class="agenda-oc-field" style="order:2">
                    <label class="form-label fw-semibold text-dark mb-1" for="eventDetailOcMember">Prestador(a) do serviço <span class="text-danger">*</span></label>
                    <select id="eventDetailOcMember" class="form-select form-select-sm">
                        <option value="">Selecionar</option>
                    </select>
                </div>
                <div class="agenda-oc-field" style="order:3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1 agenda-oc-services-head">
                        <label class="form-label fw-semibold text-dark mb-0" for="eventDetailOcService">Serviços <span class="text-danger">*</span></label>
                        <div id="eventDetailOcAddMoreServicesWrap" class="d-none flex-shrink-0">
                            <button type="button" id="eventDetailOcAddMoreServicesBtn" class="btn btn-outline-primary agenda-oc-add-services-btn rounded-pill d-inline-flex align-items-center gap-1">
                                <i class="ph ph-plus" aria-hidden="true"></i>
                                <span>Adicionar serviços</span>
                            </button>
                        </div>
                    </div>
                    <div id="eventDetailOcServiceSelectWrap">
                        <select id="eventDetailOcService" class="form-select form-select-sm" disabled>
                            <option value="">Escolha primeiro o prestador(a)</option>
                        </select>
                    </div>
                    <div id="eventDetailOcSelectedServicesList" class="mt-2 d-none"></div>
                    <div id="eventDetailOcFeesList" class="mt-2 d-none"></div>
                </div>
                <div class="agenda-oc-field" style="order:4">
                    <label class="form-label fw-semibold text-dark mb-1" for="eventDetailOcObs">Notas da Marcação</label>
                    <textarea class="form-control form-control-sm" id="eventDetailOcObs" name="description" rows="3" placeholder="Escreva uma nota sobre esta marcação"></textarea>
                </div>
                <div class="agenda-oc-field" style="order:5">
                    <div class="mb-2 d-none" id="eventDetailHorarioAvisoWrap">
                        <div class="alert alert-warning mb-0 py-2 small" id="eventDetailHorarioAviso" role="alert">
                            <i class="ph ph-warning-circle me-1"></i>
                            Horário fora do período habitual da loja ({{ $storeHoursLabel ?? '09:00–20:00' }}) ou do membro. Pode guardar na mesma, se for excecional.
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label fw-semibold text-dark mb-1" for="eventDetailOcDate">Data <span class="text-danger">*</span></label>
                            <input type="text" id="eventDetailOcDate" class="form-control" placeholder="Selecionar data" autocomplete="off">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold text-dark mb-1" for="eventDetailOcTime">Hora <span class="text-danger">*</span></label>
                            <select id="eventDetailOcTime" class="form-select"></select>
                        </div>
                    </div>
                </div>
                @if(auth()->user()->isAdmin())
                <div class="agenda-oc-field d-none mt-2 pt-2 border-top" id="eventDetailActivityLogWrap" style="order:99">
                    <button type="button" class="btn btn-link btn-sm px-0 text-decoration-none d-inline-flex align-items-center gap-1" id="eventDetailViewLogsBtn">
                        <i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i>
                        Ver logs
                    </button>
                </div>
                @endif
            </div>
        </form>
    </div>
    <div class="agenda-marcacao-test-offcanvas-footer border-top flex-column align-items-stretch" id="eventDetailOffcanvasFooter">
        <div class="d-flex align-items-center pb-2 mb-1 border-bottom border-light" id="eventDetailTotalRow">
            <div class="event-detail-total-line flex-grow-1 min-w-0" id="eventDetailTotalLeft">
                <span class="event-detail-total-line__part" id="eventDetailTotalMain">
                    <span class="small text-muted">Total:</span>
                    <span class="fw-semibold text-body" id="eventDetailTotalPrice">0,00 €</span>
                </span>
                <span class="event-detail-total-line__part d-none small text-muted" id="eventDetailReservaSummary">
                    <span id="eventDetailReservaSummaryText">Pré-pagamento <span class="fw-semibold text-body" id="eventDetailReservaAmount">0,00 €</span> &bull; Falta pagar <span class="fw-semibold text-body" id="eventDetailFaltaPagarAmount">0,00 €</span></span>
                </span>
                <span class="event-detail-total-line__part d-none small text-muted" id="eventDetailPagoSummary">
                    <span id="eventDetailPagoSummaryText">Pré-pagamento <span class="fw-semibold text-body" id="eventDetailReservaAmountPaid">0,00 €</span> &bull; Pagamento final <span class="fw-semibold text-body" id="eventDetailPagoAmount">0,00 €</span></span>
                </span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between w-100 event-detail-oc-footer-actions" id="eventDetailFooterActionsRow">
            <div class="d-flex flex-wrap gap-2 align-items-center" id="eventDetailPaymentWrap">
                <button type="button" class="btn btn-outline-primary btn-sm d-none" id="eventDetailReservaBtn" data-payment-flow-label="Pré-pagamento">Pré-pagamento</button>
                <button type="button" class="btn btn-success btn-sm d-none" id="eventDetailPaymentBtn" data-payment-flow-label="Pagamento">Pagamento</button>
                <div class="dropup d-none" id="eventDetailPaymentDropup">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" id="eventDetailPaymentDropupToggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Pagamento
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="eventDetailPaymentDropupToggle" id="eventDetailPaymentDropupMenu">
                        <li>
                            <button class="dropdown-item d-flex justify-content-between align-items-center gap-2" type="button" id="eventDetailPayCurrentBtn">
                                <span>Pagar esta marcação</span>
                                <span class="small fw-semibold" id="eventDetailPayCurrentAmount">0,00 €</span>
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><div class="dropdown-item-text small text-muted fw-semibold" id="eventDetailPayAllHint">+0 marcações por pagar hoje</div></li>
                        <li>
                            <button class="dropdown-item d-flex justify-content-between align-items-center gap-2 fw-semibold" type="button" id="eventDetailPayAllBtn" data-payment-flow-label="Pagar todas do dia">
                                <span>Pagar todas do dia</span>
                                <span class="small fw-semibold" id="eventDetailPayAllAmount">0,00 €</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm d-none" id="eventDetailOpenCashRegisterBtn" data-crm-cash-register-trigger="open" data-crm-cash-register-fixed="open">Abrir caixa</button>
                <span class="event-detail-paid-badge d-none" id="eventDetailPagoBadge" role="status" aria-label="Pago">
                    <i class="ph ph-check" aria-hidden="true"></i>
                    <span>Pago</span>
                </span>
            </div>
            <div class="d-none d-flex flex-wrap gap-1 align-items-center ms-auto" id="eventDetailFaturasWrap"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="eventDetailActivityLogModal" tabindex="-1" aria-labelledby="eventDetailActivityLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="eventDetailActivityLogModalLabel">Histórico da marcação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body py-3" id="eventDetailActivityLogBody">
                <p class="text-muted text-center py-3 mb-0">A carregar…</p>
            </div>
        </div>
    </div>
</div>

@include('partials.payment-modal', ['posGorjetaEnabled' => $posGorjetaEnabled ?? true])
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.1/build/js/intlTelInput.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/pt.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/pt.js"></script>

@php
    $me = auth()->user();
    $agendaPermissions = [
        'isPrestador' => $me->isPrestador(),
        'canCreateMarcacao' => $me->canCreateMarcacao(),
        'canProcessPayments' => $me->canProcessPayments(),
        'canViewClientContacts' => $me->canViewClientContactDetails(),
        'canViewClientProfile' => $me->canViewClientProfile(),
        'canViewInvoices' => $me->canViewInvoices(),
        'canReassignMarcacao' => $me->canReassignMarcacao(),
        'canChangeMarcacaoClient' => $me->canChangeMarcacaoClient(),
        'prestadorAllowedStatuses' => $me->prestadorAllowedMarcacaoStatuses(),
        'prestadorEditableStatuses' => $me->prestadorEditableMarcacaoStatuses(),
    ];
    $agendaEventsBase = rtrim(url('agenda/events'), '/');
    $agendaClientsBase = rtrim(url('agenda/clients'), '/');
    $usersForConsultant = ($users ?? collect())->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    $memberWeeklySchedules = collect($users ?? [])
        ->filter(fn ($u) => filled($u->agent?->weekly_schedule))
        ->mapWithKeys(fn ($u) => [(string) $u->id => $u->agent->weekly_schedule]);
    if ($me && $me->agent && filled($me->agent->weekly_schedule) && ! $memberWeeklySchedules->has((string) $me->id)) {
        $memberWeeklySchedules->put((string) $me->id, $me->agent->weekly_schedule);
    }
    $memberWeeklySchedules = $memberWeeklySchedules->all();
@endphp
<script>
window.AGENDA_CONFIG = {
    csrf: @json(csrf_token()),
    eventsUrl: @json(route('agenda.events')),
    resourcesUrl: @json(route('agenda.resources')),
    clientesBaseUrl: @json(url('clientes')),
    currentUserIsAdmin: @json(auth()->user()->role === \App\Models\User::ROLE_ADMIN),
    permissions: @json($agendaPermissions),
    authId: @json(auth()->id()),
    authName: @json(auth()->user()->name ?? 'Eu'),
    authEmail: @json(auth()->user()->email ?? ''),
    agendaMembersServicesUrl: @json(url('agenda/members')),
    agendaClientsUrl: @json(url('agenda/clients')),
    agendaClientWalletUrl: @json(url('agenda/clients')),
    urlEvents: @json(url('agenda/events')),
    urlEventsStore: @json(route('agenda.events.store')),
    agendaCheckoutStoreUrl: @json(route('agenda.checkout.store')),
    agendaCheckoutMbwayIntentUrl: @json(route('agenda.checkout.mbway.intent')),
    agendaCheckoutMbwayFinalizeUrl: @json(route('agenda.checkout.mbway.finalize')),
    agendaSameDayPayableUrl: @json($agendaEventsBase . '/__EVENT_ID__/same-day-payable'),
    bookingDepositPercent: @json((int) config('booking.deposit_percent')),
    agendaDepositShowUrl: @json($agendaEventsBase . '/__EVENT_ID__/deposit'),
    agendaDepositStoreUrl: @json($agendaEventsBase . '/__EVENT_ID__/deposit'),
    agendaDepositMbwayIntentUrl: @json($agendaEventsBase . '/__EVENT_ID__/deposit/mbway/intent'),
    agendaDepositMbwayFinalizeUrl: @json($agendaEventsBase . '/__EVENT_ID__/deposit/mbway/finalize'),
    agendaDepositCardUrl: @json($agendaEventsBase . '/__EVENT_ID__/deposit/card'),
    agendaClientSavedCardsUrl: @json($agendaClientsBase . '/__CLIENT_ID__/saved-cards'),
    agendaEventActivityLogUrl: @json($agendaEventsBase . '/__EVENT_ID__/activity-log'),
    salesRevertUrl: @json(url('sales')),
    salesFinalizeInvoiceUrl: @json(url('sales')),
    urlOpportunities: @json(url('opportunities')),
    urlLeads: @json(url('leads')),
    usersForConsultant: @json($usersForConsultant),
    nationalHolidaysPt: @json($nationalHolidaysPt ?? []),
    memberWeeklySchedules: @json($memberWeeklySchedules ?? []),
    storeWeeklySchedule: @json($storeWeeklySchedule ?? []),
    agendaSlotMin: @json($agendaSlotMin ?? '09:00'),
    agendaSlotMax: @json($agendaSlotMax ?? '20:00'),
    cashRegisterOpen: @json($cashRegisterOpen ?? false),
    onlineBookingPaymentRequired: @json($onlineBookingPaymentRequired ?? true),
};
</script>
<script src="{{ static_asset('template/js/agenda.js') }}"></script>

@endsection