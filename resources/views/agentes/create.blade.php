@extends('partials.layouts.main')
@section('title', 'Novo Membro | Beauty CRM')
@section('css')
@include('clientes.partials.intl-phone-css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<style>.category-color-choice .ri-circle-fill { font-size: 1rem; }</style>
@endsection
@section('content')

<form action="{{ route('equipa.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="uedit-grid">
        <!-- Left Sidebar -->
        <div class="uedit-sidebar">
            <!-- Profile Image -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-avatar-wrap">
                        <img src="{{ asset('template/img/avatars/avatar-1.webp') }}" alt="Profile" class="uedit-avatar" id="profilePreview">
                        <div class="mt-3">
                            <input type="file" name="avatar" class="form-control" id="profileImage" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div class="small text-muted mt-2 text-center">
                            Permitido: JPG, PNG, WEBP. Máx. 2MB
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Estado da Conta</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Estado <span class="text-danger">*</span></label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach(\App\Models\Agent::statusLabels() as $value => $label)
                                <option value="{{ $value }}" {{ old('status', 'active') == $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label for="agentColorSelect" class="form-label">Cor na agenda</label>
                        <select class="form-select" id="agentColorSelect" name="color">
                            <option value="">Selecionar cor...</option>
                            <option value="#bfdbfe" {{ old('color') == '#bfdbfe' ? 'selected' : '' }}>Azul Céu</option>
                            <option value="#93c5fd" {{ old('color') == '#93c5fd' ? 'selected' : '' }}>Azul Claro</option>
                            <option value="#a5b4fc" {{ old('color') == '#a5b4fc' ? 'selected' : '' }}>Azul Índigo</option>
                            <option value="#c7d2fe" {{ old('color') == '#c7d2fe' ? 'selected' : '' }}>Azul Lavanda</option>
                            <option value="#ddd6fe" {{ old('color') == '#ddd6fe' ? 'selected' : '' }}>Lavanda</option>
                            <option value="#e9d5ff" {{ old('color') == '#e9d5ff' ? 'selected' : '' }}>Lilás</option>
                            <option value="#f3e8ff" {{ old('color') == '#f3e8ff' ? 'selected' : '' }}>Roxo Pastel</option>
                            <option value="#fbcfe8" {{ old('color') == '#fbcfe8' ? 'selected' : '' }}>Rosa Pastel</option>
                            <option value="#fecdd3" {{ old('color') == '#fecdd3' ? 'selected' : '' }}>Rosa Claro</option>
                            <option value="#fda4af" {{ old('color') == '#fda4af' ? 'selected' : '' }}>Coral Suave</option>
                            <option value="#fed7aa" {{ old('color') == '#fed7aa' ? 'selected' : '' }}>Laranja Pastel</option>
                            <option value="#fde68a" {{ old('color') == '#fde68a' ? 'selected' : '' }}>Âmbar Claro</option>
                            <option value="#fef9c3" {{ old('color') == '#fef9c3' ? 'selected' : '' }}>Amarelo Pastel</option>
                            <option value="#d9f99d" {{ old('color') == '#d9f99d' ? 'selected' : '' }}>Verde Lima</option>
                            <option value="#bbf7d0" {{ old('color') == '#bbf7d0' ? 'selected' : '' }}>Verde Menta</option>
                            <option value="#99f6e4" {{ old('color') == '#99f6e4' ? 'selected' : '' }}>Verde Água</option>
                            <option value="#a5f3fc" {{ old('color') == '#a5f3fc' ? 'selected' : '' }}>Ciano Claro</option>
                            <option value="#bae6fd" {{ old('color') == '#bae6fd' ? 'selected' : '' }}>Azul Gelo</option>
                        </select>
                        @error('color')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Main Area -->
        <div>
            <!-- Dados gerais -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Dados gerais</div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Ex: João Silva" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="agentCreatePhone">Telefone</label>
                                <input type="tel" id="agentCreatePhone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" autocomplete="tel">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="email@exemplo.com" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Mínimo 8 caracteres" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Password <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Repita a password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Membro <span class="text-danger">*</span></label>
                                <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                    @foreach(\App\Models\User::roles() as $value => $label)
                                        <option value="{{ $value }}" {{ old('role', 'prestador') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @include('agentes.partials.specialization-field', [
                                'currentRole' => old('role', 'prestador'),
                                'specializationValue' => old('specialization'),
                            ])
                            @include('agentes.partials.commission-field', [
                                'commissionRate' => old('commission_rate'),
                                'commissionUnit' => old('commission_unit', \App\Models\Agent::COMMISSION_UNIT_PERCENT),
                            ])
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados Pessoais -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Dados Pessoais</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIF</label>
                                <input type="text" name="nif" class="form-control @error('nif') is-invalid @enderror" value="{{ old('nif') }}" placeholder="123456789" maxlength="20">
                                @error('nif')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="text" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date') }}" data-crm-datepicker data-max-date="{{ date('Y-m-d', strtotime('-18 years')) }}" autocomplete="off" placeholder="dd/mm/aaaa">
                                @error('birth_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Género</label>
                                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                    <option value="">— Selecionar —</option>
                                    @foreach(\App\Models\Agent::genders() as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nacionalidade</label>
                                <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality') }}" placeholder="Ex: Portuguesa">
                                @error('nationality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado Civil</label>
                                <select name="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                    <option value="">— Selecionar —</option>
                                    @foreach(\App\Models\Agent::maritalStatuses() as $value => $label)
                                        <option value="{{ $value }}" {{ old('marital_status') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('marital_status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Morada -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Morada</div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Morada</label>
                                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" placeholder="Ex: Rua das Flores, 123">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Porta</label>
                                <input type="text" name="door" class="form-control @error('door') is-invalid @enderror" value="{{ old('door') }}" placeholder="Ex: 1A" maxlength="10">
                                @error('door')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Andar</label>
                                <input type="text" name="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ old('floor') }}" placeholder="Ex: 2º" maxlength="10">
                                @error('floor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Lado</label>
                                <input type="text" name="side" class="form-control @error('side') is-invalid @enderror" value="{{ old('side') }}" placeholder="Ex: Esq." maxlength="10">
                                @error('side')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Código Postal</label>
                                <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code') }}" placeholder="Ex: 3800-000" maxlength="20">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Localidade</label>
                                <input type="text" name="locality" class="form-control @error('locality') is-invalid @enderror" value="{{ old('locality') }}" placeholder="Ex: Aveiro">
                                @error('locality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('agentes.partials.weekly-schedule', ['weeklySchedule' => old('weekly_schedule', null)])

            <!-- Serviços associados -->
            <div class="card">
                <div class="card-body">
                    @include('agentes.partials.services-by-category', [
                        'categories' => $categories,
                        'selectedServiceIds' => old('service_ids', []),
                    ])
                </div>
            </div>

            <!-- Form Actions -->
            <div class="uedit-form-actions">
                <a href="{{ route('equipa.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-check me-1"></i> Criar Membro
                </button>
            </div>
        </div>
    </div>
</form>

@endsection

@section('js')
<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    (function() {
        if (typeof Choices === 'undefined') return;
        function addClassesToElement(el, classes) {
            var arr = Array.isArray(classes) ? classes : [classes];
            arr.forEach(function(c) { if (c) el.classList.add(c); });
        }
        function categoryColorChoiceTemplate(templateOptions, data, itemSelectText, groupName) {
            var cn = templateOptions.classNames;
            var rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
            var div = document.createElement('div');
            div.id = data.elementId || '';
            addClassesToElement(div, cn.item);
            addClassesToElement(div, cn.itemChoice);
            div.innerHTML = '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>';
            if (data.selected) addClassesToElement(div, cn.selectedState);
            if (data.placeholder) addClassesToElement(div, cn.placeholder);
            div.setAttribute('role', data.group ? 'treeitem' : 'option');
            div.dataset.choice = '';
            div.dataset.id = String(data.id != null ? data.id : '');
            div.dataset.value = rawValue;
            if (itemSelectText) div.dataset.selectText = itemSelectText;
            if (data.group) div.dataset.groupId = String(data.group.id != null ? data.group.id : '');
            if (data.disabled) {
                addClassesToElement(div, cn.itemDisabled);
                div.dataset.choiceDisabled = '';
                div.setAttribute('aria-disabled', 'true');
            } else {
                addClassesToElement(div, cn.itemSelectable);
                div.dataset.choiceSelectable = '';
                div.setAttribute('aria-selected', data.selected ? 'true' : 'false');
            }
            return div;
        }
        function categoryColorItemTemplate(templateOptions, data, removeItemButton) {
            var cn = templateOptions.classNames;
            var rawValue = typeof data.value === 'string' ? data.value : String(data.value || '');
            var div = document.createElement('div');
            addClassesToElement(div, cn.item);
            div.innerHTML = rawValue ? '<span class="category-color-choice d-inline-flex align-items-center gap-2"><i class="ri-circle-fill" style="color:' + rawValue.replace(/"/g, '&quot;') + '"></i> ' + (data.label || '').replace(/</g, '&lt;').replace(/&/g, '&amp;') + '</span>' : (templateOptions.placeholderValue || 'Selecionar cor...');
            div.dataset.item = '';
            div.dataset.id = String(data.id != null ? data.id : '');
            div.dataset.value = rawValue;
            if (this._isSelectElement) {
                div.setAttribute('aria-selected', 'true');
                div.setAttribute('role', 'option');
            }
            if (data.placeholder) {
                div.classList.add(cn.placeholder);
                div.dataset.placeholder = '';
            }
            addClassesToElement(div, data.highlighted ? cn.highlightedState : cn.itemSelectable);
            return div;
        }
        var opts = { searchEnabled: false, itemSelectText: '', shouldSort: false, allowHTML: true, callbackOnCreateTemplates: function(t, e, c) { return { choice: categoryColorChoiceTemplate, item: categoryColorItemTemplate }; } };
        var el = document.getElementById('agentColorSelect');
        if (el && !el.closest('.choices')) new Choices(el, opts);
    })();
</script>
@include('clientes.partials.intl-phone-init', ['phoneInputId' => 'agentCreatePhone', 'phoneOptional' => true])
<script>
(function() {
    var roleSel = document.querySelector('select[name="role"]');
    var wrap = document.getElementById('agentSpecializationFieldWrap');
    var specSel = wrap ? wrap.querySelector('select[name="specialization"]') : null;
    var rolesWith = @json(\App\Models\User::rolesWithSpecialization());
    function sync() {
        if (!roleSel || !wrap) return;
        var show = rolesWith.indexOf(roleSel.value) !== -1;
        wrap.classList.toggle('d-none', !show);
        if (!show && specSel) specSel.value = '';
    }
    if (roleSel) { roleSel.addEventListener('change', sync); sync(); }
})();
(function() {
    var input = document.getElementById('commissionRateInput');
    var hidden = document.getElementById('commissionUnitHidden');
    var btns = document.querySelectorAll('.commission-unit-btn');
    if (!input || !hidden || !btns.length) return;
    function syncCommissionMax() {
        if (hidden.value === '{{ \App\Models\Agent::COMMISSION_UNIT_PERCENT }}') {
            input.setAttribute('max', '100');
        } else {
            input.removeAttribute('max');
        }
    }
    function setUnit(unit) {
        hidden.value = unit;
        btns.forEach(function(b) {
            var on = b.getAttribute('data-unit') === unit;
            b.classList.toggle('btn-primary', on);
            b.classList.toggle('btn-outline-secondary', !on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        syncCommissionMax();
    }
    btns.forEach(function(b) {
        b.addEventListener('click', function() {
            setUnit(b.getAttribute('data-unit'));
        });
    });
    setUnit(hidden.value || '{{ \App\Models\Agent::COMMISSION_UNIT_PERCENT }}');
})();
</script>
@endsection
