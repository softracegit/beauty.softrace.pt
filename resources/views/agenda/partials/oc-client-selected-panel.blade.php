@php
    $pfx = $prefix ?? 'eventDetailOc';
    $crmPrivacyLocked = app(\App\Support\CrmPrivacyLock::class)->isActive();
@endphp
{{-- Painel vertical do cliente (offcanvas detalhe / nova marcação) --}}
<div id="{{ $pfx }}ClientSelectedCard" class="agenda-oc-client-selected-card agenda-oc-client-selected-card--panel d-none{{ !empty($class) ? ' ' . $class : '' }}">
    <div class="agenda-oc-client-panel">
        <div class="agenda-oc-client-panel__hero">
            <a id="{{ $pfx }}ClientProfileAvatarLink" href="#" class="agenda-oc-client-card__profile-link agenda-oc-client-card__avatar-link text-decoration-none">
                <div class="agenda-oc-client-card__avatar">
                    <img id="{{ $pfx }}ClientAvatar" src="" alt="" class="uview-avatar agenda-oc-client-card__avatar-media d-none">
                    <div id="{{ $pfx }}ClientAvatarFallback" class="uview-avatar uview-avatar-initials agenda-oc-client-card__avatar-media d-flex align-items-center justify-content-center d-none" aria-hidden="true">
                        <span id="{{ $pfx }}ClientAvatarInitials" class="agenda-oc-client-card__avatar-initials">?</span>
                    </div>
                </div>
            </a>
            <div class="agenda-oc-client-panel__hero-meta">
                <div class="agenda-oc-client-panel__name-row" data-profile-field="name">
                    <strong id="{{ $pfx }}ClientSelectedName" class="agenda-oc-client-name agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">…</strong>
                    <input type="text" id="{{ $pfx }}ClientNameInput" class="agenda-oc-client-panel__name-input d-none" maxlength="255" autocomplete="name" placeholder="Nome e apelido" aria-label="Nome do cliente">
                </div>
                {{-- Visível em mobile (dados colapsados); oculto em desktop (está na lista de dados) --}}
                <div id="{{ $pfx }}ClientHeroPhone" class="agenda-oc-client-phone agenda-oc-client-panel__hero-phone">…</div>
                <div class="agenda-oc-client-panel__hero-actions" role="group" aria-label="Ações do cliente">
                    <a id="{{ $pfx }}ClientProfileIconLink" href="#" class="btn btn-link p-0 lh-1 text-body-secondary agenda-oc-client-profile-btn text-decoration-none{{ $crmPrivacyLocked ? ' d-none' : '' }}" title="Ver perfil" aria-label="Ver perfil">
                        <i class="ph ph-eye" aria-hidden="true"></i>
                    </a>
                    <button type="button" class="btn btn-link p-0 lh-1 text-body-secondary agenda-oc-client-change-btn" id="{{ $pfx }}ClientEditBtn" title="Alterar cliente" aria-label="Alterar cliente">
                        <i class="ph ph-arrows-left-right" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="uview-header-tags agenda-oc-client-card__tags agenda-oc-client-panel__tags">
                    <span id="{{ $pfx }}ClientTagsMount" class="agenda-oc-client-tags-mount"></span>
                </div>
            </div>
        </div>

        <div id="{{ $pfx }}ClientBirthdayBanner" class="agenda-client-birthday-banner d-none" role="status" aria-live="polite">
            <i class="ph ph-gift agenda-client-birthday-banner__icon" aria-hidden="true"></i>
            <span id="{{ $pfx }}ClientBirthdayText" class="agenda-client-birthday-banner__text"></span>
        </div>

        {{-- Alergias: ficam no cliente (não nesta marcação); só a equipa edita — o cliente no Booking só deixa notas --}}
        <div class="agenda-oc-client-alerts-wrap" id="{{ $pfx }}ClientAllergiesViewField" data-profile-field="allergies">
            <div class="agenda-oc-client-alerts">
                <div class="agenda-oc-client-alerts__head">
                    <div class="agenda-oc-client-alerts__title">
                        <i class="ph ph-warning-circle" aria-hidden="true"></i>
                        <span>Alergias ou contraindicações?</span>
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add agenda-oc-client-alerts__add d-none" id="{{ $pfx }}ClientAllergiesAddBtn">Adicionar</button>
                </div>
                <div class="agenda-oc-client-alerts__value">
                    <span id="{{ $pfx }}ClientSelectedAllergies" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                    <input type="text" id="{{ $pfx }}ClientAllergiesInput" class="agenda-oc-client-panel__inline-input d-none" maxlength="2000" placeholder="Alergias, sensibilidades, avisos…">
                </div>
            </div>
        </div>

        <div class="agenda-oc-client-panel__details" id="{{ $pfx }}ClientDetails">
            <button
                type="button"
                class="agenda-oc-client-panel__more-toggle"
                id="{{ $pfx }}ClientDetailsToggle"
                aria-expanded="false"
                aria-controls="{{ $pfx }}ClientProfileView"
            >
                <span class="agenda-oc-client-panel__more-toggle-label">Ver mais dados</span>
                <i class="ph ph-caret-down agenda-oc-client-panel__more-toggle-icon" aria-hidden="true"></i>
            </button>

            <div class="agenda-oc-client-panel__fields agenda-oc-client-panel__more" id="{{ $pfx }}ClientProfileView">
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientPhoneViewField" data-profile-field="phone">
                        <div class="form-label agenda-oc-client-panel__label">Telemóvel</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedPhone" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientPhoneAddBtn">Adicionar</button>
                            <input type="tel" id="{{ $pfx }}ClientPhoneInput" class="agenda-oc-client-panel__inline-input d-none" maxlength="50" placeholder="Telemóvel" autocomplete="tel">
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientEmailViewField" data-profile-field="email">
                        <div class="form-label agenda-oc-client-panel__label">Email</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedEmail" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientEmailAddBtn">Adicionar</button>
                            <input type="email" id="{{ $pfx }}ClientEmailInput" class="agenda-oc-client-panel__inline-input d-none" placeholder="email@exemplo.com" autocomplete="email">
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row agenda-oc-client-nif-row" data-profile-field="nif">
                        <div class="form-label agenda-oc-client-panel__label">NIF</div>
                        <div id="{{ $pfx }}ClientNifDisplayWrap" class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedNif" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientNifAddBtn">Adicionar</button>
                            <input type="text" id="{{ $pfx }}ClientNifInput" class="agenda-oc-client-panel__inline-input agenda-oc-client-nif-input d-none" maxlength="9" inputmode="numeric" pattern="[0-9]*" placeholder="9 dígitos" autocomplete="off">
                            <button type="button" class="d-none" id="{{ $pfx }}ClientNifEditBtn" tabindex="-1" aria-hidden="true"></button>
                            <button type="button" class="d-none" id="{{ $pfx }}ClientNifSaveBtn" tabindex="-1" aria-hidden="true"></button>
                            <button type="button" class="d-none" id="{{ $pfx }}ClientNifCancelBtn" tabindex="-1" aria-hidden="true"></button>
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientBirthDateViewField" data-profile-field="birth_date">
                        <div class="form-label agenda-oc-client-panel__label">D. Nasc.</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedBirthDate" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientBirthDateAddBtn">Adicionar</button>
                            <input type="text" id="{{ $pfx }}ClientBirthDateInput" class="agenda-oc-client-panel__inline-input d-none" data-crm-datepicker data-max-date="{{ date('Y-m-d') }}" placeholder="dd/mm/aaaa" autocomplete="off">
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientOrigemViewField" data-profile-field="origem">
                        <div class="form-label agenda-oc-client-panel__label">Origem</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedOrigem" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientOrigemAddBtn">Adicionar</button>
                            <select id="{{ $pfx }}ClientOrigemInput" class="agenda-oc-client-panel__inline-input agenda-oc-client-panel__inline-select d-none" aria-label="Origem do cliente">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::origemGroups() as $group => $options)
                                    <optgroup label="{{ $group }}">
                                        @foreach($options as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientProfissaoViewField" data-profile-field="profissao">
                        <div class="form-label agenda-oc-client-panel__label">Profissão</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedProfissao" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientProfissaoAddBtn">Adicionar</button>
                            <input type="text" id="{{ $pfx }}ClientProfissaoInput" class="agenda-oc-client-panel__inline-input d-none" maxlength="255" placeholder="Ex: Designer, Médica…" autocomplete="organization-title">
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientNationalityViewField" data-profile-field="nationality">
                        <div class="form-label agenda-oc-client-panel__label">Nacionalidade</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedNationality" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientNationalityAddBtn">Adicionar</button>
                            <input type="text" id="{{ $pfx }}ClientNationalityInput" class="agenda-oc-client-panel__inline-input d-none" maxlength="100" placeholder="Ex: Portuguesa" autocomplete="country-name">
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row" id="{{ $pfx }}ClientPreferredScheduleViewField" data-profile-field="preferred_schedule">
                        <div class="form-label agenda-oc-client-panel__label">Horário pref.</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedPreferredSchedule" class="agenda-oc-client-panel__value-text" role="button" tabindex="0" title="Clique para editar">—</span>
                            <button type="button" class="btn btn-link btn-sm p-0 agenda-oc-client-panel__add d-none" id="{{ $pfx }}ClientPreferredScheduleAddBtn">Adicionar</button>
                            <select id="{{ $pfx }}ClientPreferredScheduleInput" class="agenda-oc-client-panel__inline-input agenda-oc-client-panel__inline-select d-none" aria-label="Horário preferido">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::preferredSchedules() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="agenda-oc-client-panel__field agenda-oc-client-panel__field--row agenda-oc-client-panel__field--readonly" id="{{ $pfx }}ClientSinceViewField">
                        <div class="form-label agenda-oc-client-panel__label">Cliente desde</div>
                        <div class="agenda-oc-client-panel__value">
                            <span id="{{ $pfx }}ClientSelectedSince" class="agenda-oc-client-panel__value-text agenda-oc-client-panel__value-text--readonly">—</span>
                        </div>
                    </div>
            </div>

            {{-- Links ocultos: JS de perfil / marcações ainda referencia estes IDs --}}
            <a id="{{ $pfx }}ClientProfileLink" href="#" class="d-none" tabindex="-1" aria-hidden="true"></a>
            <a id="{{ $pfx }}ClientMarcacoesLink" href="#" class="d-none" tabindex="-1" aria-hidden="true"></a>
        </div>
    </div>
</div>
