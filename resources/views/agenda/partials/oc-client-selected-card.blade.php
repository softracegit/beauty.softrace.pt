@php
    $pfx = $prefix ?? 'agendaOc';
    $crmPrivacyLocked = app(\App\Support\CrmPrivacyLock::class)->isActive();
@endphp
<div id="{{ $pfx }}ClientSelectedCard" class="agenda-oc-client-selected-card d-none{{ !empty($class) ? ' ' . $class : '' }}">
    <div class="agenda-oc-client-card">
        <a id="{{ $pfx }}ClientProfileAvatarLink" href="#" class="agenda-oc-client-card__profile-link agenda-oc-client-card__avatar-link text-decoration-none">
            <div class="agenda-oc-client-card__avatar">
                <img id="{{ $pfx }}ClientAvatar" src="" alt="" class="uview-avatar agenda-oc-client-card__avatar-media d-none">
                <div id="{{ $pfx }}ClientAvatarFallback" class="uview-avatar uview-avatar-initials agenda-oc-client-card__avatar-media d-flex align-items-center justify-content-center d-none" aria-hidden="true">
                    <i class="ph ph-user agenda-oc-client-card__avatar-icon"></i>
                </div>
            </div>
        </a>
        <div class="agenda-oc-client-card__body">
            <a id="{{ $pfx }}ClientProfileNameLink" href="#" class="agenda-oc-client-card__profile-link text-decoration-none d-inline-block min-w-0">
                <strong id="{{ $pfx }}ClientSelectedName" class="agenda-oc-client-name">…</strong>
            </a>
            <div class="agenda-oc-client-contact-row d-flex align-items-center flex-wrap min-w-0">
                <span id="{{ $pfx }}ClientSelectedPhone" class="agenda-oc-client-phone">…</span>
                <span class="agenda-oc-client-contact-sep" aria-hidden="true">·</span>
                <div class="agenda-oc-client-nif-row d-inline-flex align-items-center gap-1 min-w-0">
                    <span id="{{ $pfx }}ClientNifDisplayWrap" class="d-inline-flex align-items-center gap-1 agenda-oc-client-nif-display">
                        <span id="{{ $pfx }}ClientSelectedNif">Sem NIF</span>
                        <button type="button" class="btn btn-link p-0 lh-1 text-body-secondary text-decoration-none agenda-oc-client-edit-btn agenda-oc-client-nif-edit-btn" id="{{ $pfx }}ClientNifEditBtn" title="Editar NIF" aria-label="Editar NIF">
                            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        </button>
                    </span>
                    <span id="{{ $pfx }}ClientNifInputWrap" class="d-none agenda-oc-client-nif-input-wrap">
                        <div class="d-flex align-items-center gap-1">
                            <input type="text" id="{{ $pfx }}ClientNifInput" class="form-control form-control-sm agenda-oc-client-nif-input" maxlength="9" inputmode="numeric" pattern="[0-9]*" placeholder="NIF">
                            <button type="button" class="btn btn-sm btn-primary px-2 py-1" id="{{ $pfx }}ClientNifSaveBtn">OK</button>
                            <button type="button" class="btn btn-link p-1 lh-1 text-body-secondary text-decoration-none" id="{{ $pfx }}ClientNifCancelBtn" title="Cancelar edição de NIF" aria-label="Cancelar edição de NIF">
                                <i class="ph ph-x" aria-hidden="true"></i>
                            </button>
                        </div>
                    </span>
                </div>
            </div>
            <div class="uview-header-tags agenda-oc-client-card__tags">
                <span id="{{ $pfx }}ClientTagsMount" class="agenda-oc-client-tags-mount"></span>
            </div>
        </div>
        <div class="agenda-oc-client-card__actions" role="group" aria-label="Ações do cliente">
            <a id="{{ $pfx }}ClientProfileLink" href="#" class="btn btn-link p-0 lh-1 text-body-secondary agenda-oc-client-profile-btn d-none text-decoration-none{{ $crmPrivacyLocked ? ' d-none' : '' }}" title="Ver perfil" aria-label="Ver perfil">
                <i class="ph ph-eye" aria-hidden="true"></i>
            </a>
            <button type="button" class="btn btn-link p-0 lh-1 text-body-secondary agenda-oc-client-change-btn" id="{{ $pfx }}ClientEditBtn" title="Trocar cliente" aria-label="Trocar cliente">
                <i class="ph ph-arrows-left-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>
