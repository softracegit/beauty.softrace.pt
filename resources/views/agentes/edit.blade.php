@extends('partials.layouts.main')
@section('title', 'Editar Membro | Beauty CRM')
@section('css')
<link href="{{ asset('template/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
<style>.category-color-choice .ri-circle-fill { font-size: 1rem; }</style>
@endsection
@section('content')

<form action="{{ route('equipa.update', $agente) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    
    <div class="uedit-grid">
        <!-- Left Sidebar -->
        <div class="uedit-sidebar">
            <!-- Profile Image -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-avatar-wrap">
                        @php
                            $avatarNum = ($agente->id % 9) + 1;
                            $avatarSrc = $agente->avatar 
                                ? asset('storage/' . $agente->avatar)
                                : asset("template/img/avatars/avatar-{$avatarNum}.webp");
                        @endphp
                        <img src="{{ $avatarSrc }}" alt="Profile" class="uedit-avatar" id="profilePreview">
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
                                <option value="{{ $value }}" {{ old('status', $agente->status) == $value ? 'selected' : '' }}>{{ $label }}</option>
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
                            @php $agentColor = old('color', $agente->color ?? ''); @endphp
                            <option value="#bfdbfe" {{ $agentColor == '#bfdbfe' ? 'selected' : '' }}>Azul Céu</option>
                            <option value="#93c5fd" {{ $agentColor == '#93c5fd' ? 'selected' : '' }}>Azul Claro</option>
                            <option value="#a5b4fc" {{ $agentColor == '#a5b4fc' ? 'selected' : '' }}>Azul Índigo</option>
                            <option value="#c7d2fe" {{ $agentColor == '#c7d2fe' ? 'selected' : '' }}>Azul Lavanda</option>
                            <option value="#ddd6fe" {{ $agentColor == '#ddd6fe' ? 'selected' : '' }}>Lavanda</option>
                            <option value="#e9d5ff" {{ $agentColor == '#e9d5ff' ? 'selected' : '' }}>Lilás</option>
                            <option value="#f3e8ff" {{ $agentColor == '#f3e8ff' ? 'selected' : '' }}>Roxo Pastel</option>
                            <option value="#fbcfe8" {{ $agentColor == '#fbcfe8' ? 'selected' : '' }}>Rosa Pastel</option>
                            <option value="#fecdd3" {{ $agentColor == '#fecdd3' ? 'selected' : '' }}>Rosa Claro</option>
                            <option value="#fda4af" {{ $agentColor == '#fda4af' ? 'selected' : '' }}>Coral Suave</option>
                            <option value="#fed7aa" {{ $agentColor == '#fed7aa' ? 'selected' : '' }}>Laranja Pastel</option>
                            <option value="#fde68a" {{ $agentColor == '#fde68a' ? 'selected' : '' }}>Âmbar Claro</option>
                            <option value="#fef9c3" {{ $agentColor == '#fef9c3' ? 'selected' : '' }}>Amarelo Pastel</option>
                            <option value="#d9f99d" {{ $agentColor == '#d9f99d' ? 'selected' : '' }}>Verde Lima</option>
                            <option value="#bbf7d0" {{ $agentColor == '#bbf7d0' ? 'selected' : '' }}>Verde Menta</option>
                            <option value="#99f6e4" {{ $agentColor == '#99f6e4' ? 'selected' : '' }}>Verde Água</option>
                            <option value="#a5f3fc" {{ $agentColor == '#a5f3fc' ? 'selected' : '' }}>Ciano Claro</option>
                            <option value="#bae6fd" {{ $agentColor == '#bae6fd' ? 'selected' : '' }}>Azul Gelo</option>
                        </select>
                        @error('color')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="uedit-danger-zone">
                <div class="uedit-danger-header">
                    <i class="ph ph-warning me-1"></i> Zona de Perigo
                </div>
                <div class="uedit-danger-body">
                    <p class="text-muted small mb-3">Uma vez eliminado um membro, não há volta atrás. Por favor, tenha a certeza.</p>
                    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAgentModal">
                        <i class="ph ph-trash me-1"></i> Eliminar Membro
                    </button>
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $agente->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $agente->user->email ?? '') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Membro <span class="text-danger">*</span></label>
                                <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                    @foreach(\App\Models\User::roles() as $value => $label)
                                        <option value="{{ $value }}" {{ old('role', $agente->user->role ?? 'prestador') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Especialização</label>
                                <input type="text" name="specialization" class="form-control @error('specialization') is-invalid @enderror" value="{{ old('specialization', $agente->specialization) }}" placeholder="Ex: Manicure, Barbeiro, Tratamento Pets">
                                @error('specialization')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Taxa de Comissão (%)</label>
                                <input type="number" name="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" value="{{ old('commission_rate', $agente->commission_rate) }}" placeholder="Ex: 5.00" step="0.01" min="0" max="100">
                                @error('commission_rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
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
                                <label class="form-label">Telefone</label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $agente->phone) }}" placeholder="+351 912 345 678">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIF</label>
                                <input type="text" name="nif" class="form-control @error('nif') is-invalid @enderror" value="{{ old('nif', $agente->nif) }}" placeholder="123456789" maxlength="20">
                                @error('nif')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $agente->birth_date?->format('Y-m-d')) }}" max="{{ date('Y-m-d', strtotime('-18 years')) }}">
                                @error('birth_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Género</label>
                                <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                    <option value="">— Selecionar —</option>
                                    @foreach(\App\Models\Agent::genders() as $value => $label)
                                        <option value="{{ $value }}" {{ old('gender', $agente->gender) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nacionalidade</label>
                                <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ old('nationality', $agente->nationality) }}" placeholder="Ex: Portuguesa">
                                @error('nationality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado Civil</label>
                                <select name="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                    <option value="">— Selecionar —</option>
                                    @foreach(\App\Models\Agent::maritalStatuses() as $value => $label)
                                        <option value="{{ $value }}" {{ old('marital_status', $agente->marital_status) == $value ? 'selected' : '' }}>{{ $label }}</option>
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
                                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $agente->address) }}" placeholder="Ex: Rua das Flores, 123">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Porta</label>
                                <input type="text" name="door" class="form-control @error('door') is-invalid @enderror" value="{{ old('door', $agente->door) }}" placeholder="Ex: 1A" maxlength="10">
                                @error('door')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Andar</label>
                                <input type="text" name="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ old('floor', $agente->floor) }}" placeholder="Ex: 2º" maxlength="10">
                                @error('floor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Lado</label>
                                <input type="text" name="side" class="form-control @error('side') is-invalid @enderror" value="{{ old('side', $agente->side) }}" placeholder="Ex: Esq." maxlength="10">
                                @error('side')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Código Postal</label>
                                <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $agente->postal_code) }}" placeholder="Ex: 3800-000" maxlength="20">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Localidade</label>
                                <input type="text" name="locality" class="form-control @error('locality') is-invalid @enderror" value="{{ old('locality', $agente->locality) }}" placeholder="Ex: Aveiro">
                                @error('locality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Serviços associados -->
            <div class="card">
                <div class="card-body">
                    @include('agentes.partials.services-by-category', [
                        'categories' => $categories,
                        'selectedServiceIds' => old('service_ids', $agente->services->pluck('id')->toArray()),
                    ])
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-body">
                    <div class="uedit-section">
                        <div class="uedit-section-title">Alterar Password</div>
                        <div class="alert alert-info d-flex align-items-center mb-3">
                            <i class="ph ph-info me-2"></i>
                            <div>Deixe em branco para manter a password atual.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nova Password</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Introduza a nova password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirmar Password</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Confirme a nova password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="uedit-form-actions">
                <a href="{{ route('equipa.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-check me-1"></i> Guardar Alterações
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Delete Agent Modal -->
<div class="modal fade" id="deleteAgentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="ph ph-warning me-2"></i>Eliminar Membro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem a certeza que deseja eliminar <strong>{{ $agente->name }}</strong>?</p>
                <p class="text-muted mb-0">Esta ação não pode ser desfeita. Todos os dados do membro serão permanentemente removidos do sistema.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('equipa.destroy', $agente) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Eliminar Membro</button>
                </form>
            </div>
        </div>
    </div>
</div>

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
@endsection
