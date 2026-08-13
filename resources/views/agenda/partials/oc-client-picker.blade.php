@php
    $pfx = $prefix ?? 'agendaOc';
@endphp
{{-- Duas áreas: Cliente existente + Novo cliente (border dashed) --}}
<div id="{{ $pfx }}ClientNotSelectedWrap" class="agenda-oc-client-picker">
    <div class="agenda-oc-client-choice-card is-active" id="{{ $pfx }}ClientSearchMode">
        <div class="agenda-oc-client-choice-card__intro">
            <span class="agenda-oc-client-choice-card__avatar" aria-hidden="true">
                <i class="ph ph-magnifying-glass"></i>
            </span>
            <span class="agenda-oc-client-choice-card__label" id="{{ $pfx }}ClientLabel">Cliente existente</span>
        </div>
        <div id="{{ $pfx }}ClientSearchWrap" class="agenda-oc-client-choice-card__body">
            <select id="{{ $pfx }}Client" class="form-select form-select-sm" data-placeholder="Pesquisar cliente" aria-label="Pesquisar cliente" aria-labelledby="{{ $pfx }}ClientLabel">
                <option value="">A carregar…</option>
            </select>
        </div>
    </div>

    <div class="agenda-oc-client-picker__or" id="{{ $pfx }}ClientPickerOr" role="separator" aria-label="ou">
        <span class="agenda-oc-client-picker__or-line" aria-hidden="true"></span>
        <span class="agenda-oc-client-picker__or-text">ou</span>
        <span class="agenda-oc-client-picker__or-line" aria-hidden="true"></span>
    </div>

    <div class="agenda-oc-client-choice-card agenda-oc-client-choice-card--new" id="{{ $pfx }}ClientNewCard">
        <button type="button" class="agenda-oc-client-choice-card__trigger" id="{{ $pfx }}ShowNewClientBtn" aria-expanded="false" aria-controls="{{ $pfx }}ClientNewMode">
            <span class="agenda-oc-client-choice-card__avatar" aria-hidden="true">
                <i class="ph ph-user-plus"></i>
            </span>
            <span class="agenda-oc-client-choice-card__label">Novo cliente</span>
        </button>
        <div id="{{ $pfx }}ClientNewMode" class="agenda-oc-client-choice-card__body agenda-oc-client-choice-card__new-form d-none">
            <div class="mb-2">
                <label class="form-label small mb-1" for="{{ $pfx }}NewClientName">Nome</label>
                <input type="text" class="form-control form-control-sm" id="{{ $pfx }}NewClientName" name="{{ $pfx }}_new_client_name" autocomplete="name" placeholder="Nome e apelido">
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1" for="{{ $pfx }}NewClientPhone">Telemóvel</label>
                <input type="tel" class="form-control" id="{{ $pfx }}NewClientPhone" autocomplete="tel" placeholder="Número de telemóvel">
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1" for="{{ $pfx }}NewClientEmail">Email</label>
                <input type="email" class="form-control form-control-sm" id="{{ $pfx }}NewClientEmail" name="{{ $pfx }}_new_client_email" autocomplete="email" placeholder="Opcional">
            </div>
            <div class="mb-2">
                <label class="form-label small mb-1" for="{{ $pfx }}NewClientNif">NIF</label>
                <input type="text" class="form-control form-control-sm" id="{{ $pfx }}NewClientNif" name="{{ $pfx }}_new_client_nif" maxlength="9" inputmode="numeric" pattern="[0-9]*" autocomplete="off" placeholder="9 dígitos (opcional)">
            </div>
            <div class="mb-3">
                <label class="form-label small mb-1" for="{{ $pfx }}NewClientBirthDate">Data de nascimento</label>
                <input type="text" class="form-control form-control-sm" id="{{ $pfx }}NewClientBirthDate" name="{{ $pfx }}_new_client_birth_date" data-crm-datepicker data-max-date="{{ date('Y-m-d') }}" autocomplete="off" placeholder="dd/mm/aaaa">
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="{{ $pfx }}NewClientSubmit">Guardar cliente</button>
                <button type="button" class="btn btn-light btn-sm border" id="{{ $pfx }}CancelNewClientBtn">Cancelar</button>
            </div>
        </div>
    </div>
</div>
