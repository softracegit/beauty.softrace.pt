{{-- Formulário partilhado para criar/editar cliente (layout uedit igual aos membros) --}}
@php
    $isEdit = isset($cliente) && $cliente;
    $model = $cliente ?? null;
    $v = function($key, $default = '') use ($model) {
        if ($model && isset($model->$key)) {
            $val = $model->$key;
            return $val instanceof \Carbon\Carbon ? $val->format('Y-m-d') : $val;
        }
        return old($key, $default);
    };
    $displayName = trim((string) ($isEdit && $model ? $model->name : old('name', '')));
    $nameParts = preg_split('/\s+/u', $displayName);
    $nameParts = array_values(array_filter($nameParts, fn ($part) => $part !== ''));
    if (count($nameParts) >= 2) {
        $firstInitial = mb_substr($nameParts[0], 0, 1, 'UTF-8');
        $lastInitial = mb_substr($nameParts[count($nameParts) - 1], 0, 1, 'UTF-8');
        $avatarInitial = mb_strtoupper($firstInitial . $lastInitial, 'UTF-8');
    } elseif (count($nameParts) === 1) {
        $avatarInitial = mb_strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'), 'UTF-8');
    } else {
        $avatarInitial = '—';
    }
    $hasAvatar = $isEdit && $model && (bool) $model->avatar;
@endphp

<div class="uedit-grid">
    <!-- Left Sidebar -->
    <div class="uedit-sidebar">
        <!-- Profile Image -->
        <div class="card">
            <div class="card-body">
                <div class="uedit-avatar-wrap">
                    @if($hasAvatar)
                        <img src="{{ asset('storage/' . $model->avatar) }}" alt="Avatar" class="uedit-avatar" id="profilePreview">
                    @else
                        <div class="uedit-avatar uedit-avatar-initials" id="profilePreview" aria-label="{{ $displayName !== '' ? $displayName : 'Cliente' }}">{{ $avatarInitial }}</div>
                    @endif
                    <div class="mt-3">
                        <input type="file" name="avatar" class="form-control" id="profileImage" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <div class="small text-muted mt-2 text-center">
                        Permitido: JPG, PNG, WEBP. Máx. 2MB
                    </div>
                </div>
            </div>
        </div>

        @if($isEdit)
        <!-- Danger Zone -->
        <div class="uedit-danger-zone">
            <div class="uedit-danger-header">
                <i class="ph ph-warning me-1"></i> Zona de Perigo
            </div>
            <div class="uedit-danger-body">
                <p class="text-muted small mb-3">Uma vez eliminado um cliente, não há volta atrás. Por favor, tenha a certeza.</p>
                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteClientModal">
                    <i class="ph ph-trash me-1"></i> Eliminar Cliente
                </button>
            </div>
        </div>
        @endif
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
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ $v('name') }}" placeholder="Ex: Maria Silva" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="{{ $isEdit ? 'clientPhone' : 'clientCreatePhone' }}">Telemóvel <span class="text-danger">*</span></label>
                            @if($isEdit)
                                {{-- name="phone" é enviado via hidden pelo intl-phone-init (E.164), para não repintar o campo visível no submit --}}
                                <input type="tel" id="clientPhone" class="form-control @error('phone') is-invalid @enderror" value="{{ $v('phone') }}" autocomplete="tel" required>
                            @else
                                <input type="tel" id="clientCreatePhone" class="form-control @error('phone') is-invalid @enderror" value="{{ $v('phone') }}" autocomplete="tel" required>
                            @endif
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ $v('email') }}" placeholder="email@exemplo.com">
                            @error('email')
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
                            <label class="form-label">NIF</label>
                            <input type="text" name="nif" class="form-control @error('nif') is-invalid @enderror" value="{{ $v('nif') }}" placeholder="123456789" maxlength="20">
                            @error('nif')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="text" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ $v('birth_date') }}" data-crm-datepicker data-max-date="{{ date('Y-m-d', strtotime('-18 years')) }}" autocomplete="off" placeholder="dd/mm/aaaa">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Género</label>
                            <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::genders() as $value => $label)
                                    <option value="{{ $value }}" {{ $v('gender') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nacionalidade</label>
                            <input type="text" name="nationality" class="form-control @error('nationality') is-invalid @enderror" value="{{ $v('nationality') }}" placeholder="Ex: Portuguesa">
                            @error('nationality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado Civil</label>
                            <select name="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::maritalStatuses() as $value => $label)
                                    <option value="{{ $value }}" {{ $v('marital_status') == $value ? 'selected' : '' }}>{{ $label }}</option>
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
                            <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ $v('address') }}" placeholder="Ex: Rua das Flores, 123">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Porta</label>
                            <input type="text" name="door" class="form-control @error('door') is-invalid @enderror" value="{{ $v('door') }}" placeholder="Ex: 1A" maxlength="10">
                            @error('door')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Andar</label>
                            <input type="text" name="floor" class="form-control @error('floor') is-invalid @enderror" value="{{ $v('floor') }}" placeholder="Ex: 2º" maxlength="10">
                            @error('floor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Lado</label>
                            <input type="text" name="side" class="form-control @error('side') is-invalid @enderror" value="{{ $v('side') }}" placeholder="Ex: Esq." maxlength="10">
                            @error('side')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Código Postal</label>
                            <input type="text" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ $v('postal_code') }}" placeholder="Ex: 3800-000" maxlength="20">
                            @error('postal_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Localidade</label>
                            <input type="text" name="locality" class="form-control @error('locality') is-invalid @enderror" value="{{ $v('locality') }}" placeholder="Ex: Aveiro">
                            @error('locality')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Distrito</label>
                            <select name="id_district" id="id_district" class="form-select @error('id_district') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach($districts as $district)
                                    <option value="{{ $district['id'] }}" {{ $v('id_district') == $district['id'] ? 'selected' : '' }}>
                                        {{ $district['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_district')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Concelho</label>
                            <select name="id_city" id="id_city" class="form-select @error('id_city') is-invalid @enderror" {{ ($selectedDistrict ?? null) && ($cities->count() ?? 0) > 0 ? '' : 'disabled' }}>
                                <option value="">— Selecionar —</option>
                                @foreach($cities ?? [] as $city)
                                    <option value="{{ $city['id'] }}" {{ $v('id_city') == $city['id'] ? 'selected' : '' }}>
                                        {{ $city['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Freguesia</label>
                            <select name="id_parish" id="id_parish" class="form-select @error('id_parish') is-invalid @enderror" {{ ($selectedCity ?? null) && ($parishes->count() ?? 0) > 0 ? '' : 'disabled' }}>
                                <option value="">— Selecionar —</option>
                                @foreach($parishes ?? [] as $parish)
                                    <option value="{{ $parish['id'] }}" {{ $v('id_parish') == $parish['id'] ? 'selected' : '' }}>
                                        {{ $parish['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_parish')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preferências -->
        <div class="card">
            <div class="card-body">
                <div class="uedit-section">
                    <div class="uedit-section-title">Preferências</div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Horário preferido</label>
                            <select name="preferred_schedule" class="form-select @error('preferred_schedule') is-invalid @enderror">
                                <option value="">— Selecionar —</option>
                                @foreach(\App\Models\Client::preferredSchedules() as $value => $label)
                                    <option value="{{ $value }}" {{ $v('preferred_schedule') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('preferred_schedule')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Observações das preferências</label>
                            <textarea name="preferences_notes" class="form-control @error('preferences_notes') is-invalid @enderror" rows="3" placeholder="Ex: Prefere corte seco, evita horário de almoço...">{{ $v('preferences_notes') }}</textarea>
                            @error('preferences_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="uedit-form-actions">
            <a href="{{ route('clientes.index') }}" class="btn btn-secondary">Cancelar</a>
            @if($isEdit)
                <a href="{{ route('clientes.show', $model) }}" class="btn btn-outline-primary">Ver</a>
            @endif
            <button type="submit" class="btn btn-primary">
                <i class="ph ph-check me-1"></i> {{ $isEdit ? 'Guardar Alterações' : 'Criar Cliente' }}
            </button>
        </div>
    </div>
</div>
